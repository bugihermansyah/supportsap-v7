<?php

namespace App\Console\Commands;

use App\Filament\Resources\BorrowRequests\BorrowRequestResource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

#[Signature('email:fetch-status')]
#[Description('Fetch unread emails from LOGISTIK to update borrow request status')]
class FetchEmailUpdates extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to fetch emails...');

        try {
            $client = Client::account('default');
            $client->connect();

            $folder = $client->getFolder('INBOX');

            // Find all unseen emails from the target sender admin-system@ptsap.co.id
            $messages = $folder->query()->unseen()->from('admin-system@ptsap.co.id')->get();
            // $messages = $folder->query()->unseen()->from('bh.ptsap@gmail.com')->get();
            
            $this->info('Found ' . $messages->count() . ' unread emails.');

            foreach ($messages as $message) {
                $subject = $message->getSubject();
                $body = $message->getTextBody();
                if (!$body) {
                    $body = $message->getHTMLBody();
                }
                
                // Remove HTML tags if any, to make matching easier
                $plainBody = strip_tags($body);

                // Try to find the REQKRM or REQAMB Number
                $reqNo = null;
                $reqType = null;
                if (preg_match('/(REQKRM|REQAMB)\/\d{4}\/\d+/i', $subject . ' ' . $plainBody, $matches)) {
                    $reqNo = strtoupper($matches[0]);
                    $reqType = strtoupper($matches[1]);
                }

                if (!$reqNo) {
                    $this->warn('No REQKRM or REQAMB Number found in email: ' . $subject);
                    $message->setFlag(['Seen']);
                    continue;
                }

                // Determine the new status
                $newStatus = null;
                $actionName = 'system_update';

                if (stripos($plainBody, 'Akan ada Pengiriman') !== false) {
                    $newStatus = 'delivery_scheduled';
                    $actionName = 'delivery_scheduled';
                } elseif (stripos($plainBody, 'Pengiriman Berhasil') !== false) {
                    $newStatus = 'delivered';
                    $actionName = 'delivered';
                } elseif (stripos($plainBody, 'Akan ada Pengambilan') !== false) {
                    $newStatus = 'pickup_scheduled';
                    $actionName = 'pickup_scheduled';
                } elseif (stripos($plainBody, 'Pengambilan Berhasil') !== false) {
                    $newStatus = 'picked_up';
                    $actionName = 'picked_up';
                }

                if (!$newStatus) {
                    $this->warn('No matching status found for Request: ' . $reqNo);
                    $message->setFlag(['Seen']);
                    continue;
                }

                // Find Borrow Request
                $borrowRequestQuery = null;
                if ($reqType === 'REQKRM') {
                    $borrowRequestQuery = BorrowRequest::where('send_no', $reqNo);
                } elseif ($reqType === 'REQAMB') {
                    $borrowRequestQuery = BorrowRequest::where('take_no', $reqNo);
                }

                if (!$borrowRequestQuery || !$borrowRequestQuery->exists()) {
                    $this->warn("Borrow Request not found for $reqType: " . $reqNo);
                    $message->setFlag(['Seen']);
                    continue;
                }

                // Extract log_at date from email text
                $logAt = null;
                if (preg_match('/tanggal\s*:\s*(\d{2}\/\d{2}\/\d{4})/i', $plainBody, $dateMatches)) {
                    try {
                        $logAt = \Carbon\Carbon::createFromFormat('d/m/Y', trim($dateMatches[1]))->format('Y-m-d');
                    } catch (\Exception $e) {
                        $logAt = null;
                    }
                }

                // Update log_status and log_at efficiently in bulk
                $updateData = [];
                if ($logAt) {
                    $updateData['log_at'] = $logAt;
                }

                if (in_array($newStatus, ['delivery_scheduled', 'delivered'])) {
                    $updateData['log_status'] = $newStatus;
                }

                if (!empty($updateData)) {
                    $borrowRequestQuery->update($updateData);
                }

                // Extract note from 'Dear' to 'Terima Kasih'
                $noteContent = "Status diupdate dari Email (IMAP)";
                if (preg_match('/(Dear[\s\S]*?Terima Kasih)/i', $plainBody, $noteMatches)) {
                    $noteContent = trim($noteMatches[1]);
                } else {
                    // Fallback to first few characters if not found
                    $noteContent = trim(mb_substr($plainBody, 0, 500)) . '...';
                }

                $emailDate = $message->getDate();
                if (is_array($emailDate) || $emailDate instanceof \Illuminate\Support\Collection) {
                    $emailDate = clone $emailDate[0];
                } elseif (is_array($emailDate) === false && is_object($emailDate)) {
                    $emailDate = clone $emailDate;
                } else {
                    $emailDate = now();
                }
                
                $emailDateFormatted = \Carbon\Carbon::parse($emailDate)->format('Y-m-d H:i:s');

                $admins = \App\Models\User::role('admin')->get();

                $statusLabels = [
                    'delivery_scheduled' => 'Delivery Scheduled',
                    'delivered' => 'Delivered',
                    'pickup_scheduled' => 'Pickup Scheduled',
                    'picked_up' => 'Picked Up',
                ];
                $statusLabel = $statusLabels[$newStatus] ?? $newStatus;

                // Process logs and notifications in chunks to keep memory usage low
                $borrowRequestQuery->with(['units.unit', 'requester', 'location'])->chunkById(100, function ($borrowRequests) use ($actionName, $logAt, $noteContent, $emailDateFormatted, $newStatus, $statusLabel, $admins) {
                    $logsToInsert = [];
                    
                    foreach ($borrowRequests as $borrowRequest) {
                        // Prepare log data for bulk insert
                        $logsToInsert[] = [
                            'borrow_request_id' => $borrowRequest->id,
                            'action_by' => 'system',
                            'action' => $actionName,
                            'date' => $logAt,
                            'note' => $noteContent,
                            'details' => json_encode($borrowRequest->units->map(function ($unit) {
                                return [
                                    'unit_id' => $unit->unit_id,
                                    'name' => $unit->unit->name ?? 'Unknown',
                                    'qty' => $unit->qty,
                                ];
                            })->toArray()),
                            'created_at' => $emailDateFormatted,
                            'updated_at' => $emailDateFormatted,
                        ];

                        // Send Database Notification
                        $requester = $borrowRequest->requester;

                        $notification = Notification::make()
                            ->title("{$statusLabel}")
                            ->icon('heroicon-o-check-circle')
                            ->body("The request {$borrowRequest->location?->name} status has been updated to {$statusLabel} by Logistics.")
                            ->actions([
                                Action::make('View')
                                    ->url(BorrowRequestResource::getUrl('edit', ['record' => $borrowRequest]))
                                    ->button()
                                    ->markAsRead(),
                            ]);

                        $notification->sendToDatabase($admins);
                        if ($requester) {
                            $notification->sendToDatabase($requester);
                        }
                    }

                    if (!empty($logsToInsert)) {
                        BorrowRequestLog::insert($logsToInsert);
                    }
                });

                $this->info("Successfully updated requests for {$reqNo} to {$newStatus}");

                // Mark as read
                $message->setFlag(['Seen']);
            }

            $this->info('Finished processing emails.');

        } catch (\Exception $e) {
            $this->error('Error fetching emails: ' . $e->getMessage());
            Log::error('FetchEmailUpdates Error: ' . $e->getMessage());
        }
    }
}
