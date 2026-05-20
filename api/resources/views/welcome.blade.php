<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SUKI CART API</title>
    <link rel="icon" type="image/png" href="{{ asset('suki-cart-logo-home.png') }}">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background-color: #f3f4f6;
            color: #111827;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        main {
            width: 100%;
            max-width: 560px;
            overflow: hidden;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        }

        header {
            padding: 44px 48px 40px;
            text-align: center;
            background: linear-gradient(135deg, #052e16 0%, #14532d 50%, #166534 100%);
        }

        .brand-mark {
            display: block;
            width: 96px;
            height: 96px;
            margin: 0 auto 20px;
            object-fit: contain;
            border-radius: 20px;
        }

        h1 {
            color: #ffffff;
            font-size: 30px;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -0.4px;
        }

        h1 span {
            color: #6ee7b7;
        }

        section {
            padding: 40px 48px;
            text-align: center;
        }

        p {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.75;
        }

        footer {
            padding: 24px 48px;
            text-align: center;
            background: #f9fafb;
            border-top: 1px solid #f3f4f6;
        }

        footer p {
            color: #d1d5db;
            font-size: 11px;
            line-height: 1.7;
        }

        @media only screen and (max-width: 600px) {
            header,
            section,
            footer {
                padding-left: 24px;
                padding-right: 24px;
            }

            h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <main>
        <header>
            <img class="brand-mark" src="{{ asset('suki-cart-logo-home.png') }}" alt="Suki Cart logo">
            <h1>SUKI CART<br><span>API</span></h1>
        </header>

        <section>
            <p>Backend services for Suki Cart are running and ready to handle authentication, stores, products and orders.</p>
        </section>

        <footer>
            <p>&copy; {{ date('Y') }} SukiCart - Your trusted online shop</p>
        </footer>
    </main>
</body>
</html>
