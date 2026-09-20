@php
    $homeUrl = \Illuminate\Support\Facades\Route::has('filament.admin.pages.dashboard')
        ? route('filament.admin.pages.dashboard')
        : url('/');
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Akses Ditolak | Support SAP</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #1f2b3a;
            --muted: #687484;
            --accent: #c7aa62;
            --accent-dark: #927636;
            --surface: #ffffff;
            --line: #e7e9ed;
            --page: #f5f6f8;
            --focus: #2563eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at 84% 14%, rgba(199, 170, 98, 0.15), transparent 28%),
                linear-gradient(135deg, #f7f8fa 0%, var(--page) 52%, #eef1f4 100%);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .error-card {
            width: min(100%, 560px);
            overflow: hidden;
            border: 1px solid rgba(31, 43, 58, 0.1);
            border-radius: 18px;
            background: var(--surface);
            box-shadow: 0 18px 50px rgba(31, 43, 58, 0.12);
        }

        .error-card__top {
            position: relative;
            padding: 28px 32px 24px;
            background: var(--ink);
            color: #fff;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .brand__mark {
            display: grid;
            width: 28px;
            height: 28px;
            place-items: center;
            border: 1px solid var(--accent);
            border-radius: 8px 8px 8px 2px;
            color: var(--accent);
            font-size: 0.7rem;
            letter-spacing: 0;
        }

        .error-code {
            margin: 34px 0 0;
            color: var(--accent);
            font-size: clamp(4rem, 14vw, 6.5rem);
            font-weight: 800;
            letter-spacing: -0.08em;
            line-height: 0.82;
        }

        .error-card__body {
            padding: 34px 32px 32px;
        }

        h1 {
            margin: 0;
            font-size: clamp(1.4rem, 4vw, 1.8rem);
            letter-spacing: -0.025em;
            line-height: 1.2;
        }

        .message {
            max-width: 44ch;
            margin: 12px 0 26px;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.65;
        }

        .action {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 46px;
            padding: 0 18px;
            border: 1px solid var(--accent-dark);
            border-radius: 9px;
            background: var(--accent);
            color: #201a0d;
            font-size: 0.94rem;
            font-weight: 700;
            text-decoration: none;
            transition: background-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
        }

        .action:hover {
            background: #d3b873;
            box-shadow: 0 8px 18px rgba(146, 118, 54, 0.2);
            transform: translateY(-1px);
        }

        .action:focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 3px;
        }

        .action__arrow {
            font-size: 1.15rem;
            line-height: 1;
        }

        @media (max-width: 540px) {
            body {
                padding: 16px;
            }

            .error-card__top,
            .error-card__body {
                padding-left: 24px;
                padding-right: 24px;
            }

            .action {
                width: 100%;
                justify-content: center;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>
<body>
    <main class="error-card" aria-labelledby="error-title">
        <div class="error-card__top">
            <div class="brand" aria-label="Support SAP">
                <span class="brand__mark" aria-hidden="true">SAP</span>
                <span>Support SAP</span>
            </div>
            <div class="error-code" aria-hidden="true">403</div>
        </div>

        <div class="error-card__body">
            <h1 id="error-title">Akses ke halaman ini ditolak</h1>
            <p class="message">
                Anda tidak memiliki permission yang diperlukan untuk membuka halaman tersebut.
                Silakan kembali ke beranda untuk melanjutkan pekerjaan.
            </p>
            <a class="action" href="{{ $homeUrl }}">
                <span class="action__arrow" aria-hidden="true">←</span>
                <span>Kembali ke Beranda</span>
            </a>
        </div>
    </main>
</body>
</html>
