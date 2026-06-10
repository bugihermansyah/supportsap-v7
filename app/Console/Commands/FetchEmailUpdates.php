<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestLog;
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

            // Find all unseen emails from the target sender
            // $messages = $folder->query()->unseen()->from('admin-system@ptsap.co.id')->get();
            $messages = $folder->query()->unseen()->from('bh.ptsap@gmail.com')->get();
            
            $this->info('Found ' . $messages->count() . ' unread emails.');

            foreach ($messages as $message) {
                $subject = $message->getSubject();
                $body = $message->getTextBody();
                if (!$body) {
                    $body = $message->getHTMLBody();
                }
                
                // Remove HTML tags if any, to make matching easier
                $plainBody = strip_tags($body);

                // Try to find the RP Number (e.g. RP-2605-000620)
                $rpNo = null;
                if (preg_match('/RP-\d{4}-\d{6}/i', $plainBody, $matches)) {
                    $rpNo = strtoupper($matches[0]);
                }

                if (!$rpNo) {
                    $this->warn('No RP Number found in email subject: ' . $subject);
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
                    $this->warn('No matching status found for RP: ' . $rpNo);
                    $message->setFlag(['Seen']);
                    continue;
                }

                // Find Borrow Request
                $borrowRequest = BorrowRequest::where('rp_no', $rpNo)->first();

                if (!$borrowRequest) {
                    $this->warn('Borrow Request not found for RP: ' . $rpNo);
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

                // Update log_status and log_at
                $updateData = [];
                if ($logAt) {
                    $updateData['log_at'] = $logAt;
                }

                if (in_array($newStatus, ['delivery_scheduled', 'delivered'])) {
                    $updateData['log_status'] = $newStatus;
                }

                if (!empty($updateData)) {
                    $borrowRequest->update($updateData);
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

                // Create Log
                $log = new BorrowRequestLog();
                $log->borrow_request_id = $borrowRequest->id;
                $log->action_by = 999;
                $log->action = $actionName;
                $log->note = $noteContent;
                $log->details = $borrowRequest->units->map(function ($unit) {
                    return [
                        'unit_id' => $unit->unit_id,
                        'name' => $unit->unit->name ?? 'Unknown',
                        'qty' => $unit->qty,
                    ];
                })->toArray();
                $log->created_at = $emailDate;
                $log->updated_at = $emailDate;
                $log->save();

                $this->info("Successfully updated {$rpNo} to {$newStatus}");
                
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
