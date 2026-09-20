@include('errors.error-page', [
    'statusCode' => '403',
    'title' => 'Akses ke halaman ini ditolak',
    'message' => 'Anda tidak memiliki permission yang diperlukan untuk membuka halaman tersebut.',
])
