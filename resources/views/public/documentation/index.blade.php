<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumentasi WhatsApp API | {{ config('app.name', 'Mirbal') }}</title>
    <meta name="robots" content="index, follow">
    <style>
        :root {
            --brand: #2563eb;
            --bg: #f8fafc;
            --border: #e2e8f0;
            --text: #1e293b;
            --muted: #64748b;
            --code-bg: #0f172a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--text);
            background: var(--bg);
            line-height: 1.6;
        }
        a { color: var(--brand); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .doc-header {
            background: #0f172a;
            color: #fff;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .doc-header h1 { font-size: 18px; margin: 0; font-weight: 600; }
        .doc-header .doc-header-sub { color: #94a3b8; font-size: 13px; }

        .doc-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            max-width: 1200px;
            margin: 0 auto;
        }
        @media (max-width: 900px) {
            .doc-layout { grid-template-columns: 1fr; }
            .doc-sidebar { position: static; border-right: none; border-bottom: 1px solid var(--border); }
        }

        .doc-sidebar {
            border-right: 1px solid var(--border);
            padding: 24px 16px;
            align-self: start;
            position: sticky;
            top: 62px;
            max-height: calc(100vh - 62px);
            overflow-y: auto;
        }
        .doc-sidebar h4 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            margin: 16px 8px 6px;
        }
        .doc-sidebar h4:first-child { margin-top: 0; }
        .doc-sidebar ul { list-style: none; margin: 0; padding: 0; }
        .doc-sidebar li a {
            display: block;
            padding: 6px 8px;
            border-radius: 6px;
            color: var(--text);
            font-size: 14px;
        }
        .doc-sidebar li a:hover { background: #eef2ff; text-decoration: none; }
        .doc-sidebar .doc-method-tag {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 4px;
            background: #e0e7ff;
            color: var(--brand);
            margin-right: 6px;
        }

        .doc-content { padding: 32px; max-width: 800px; }
        .doc-intro {
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 32px;
            font-size: 14px;
        }
        .doc-category { margin-bottom: 40px; }
        .doc-category h2 {
            font-size: 22px;
            border-bottom: 2px solid var(--border);
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .doc-category > p { color: var(--muted); margin-top: 0; }

        .doc-article {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 20px;
            scroll-margin-top: 76px;
        }
        .doc-article h3 { margin: 0 0 10px; font-size: 17px; }
        .doc-endpoint {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 13px;
            background: #f1f5f9;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
            overflow-x: auto;
        }
        .doc-method {
            font-weight: 700;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            color: #fff;
            flex-shrink: 0;
        }
        .doc-method-GET { background: #0ea5e9; }
        .doc-method-POST { background: #16a34a; }
        .doc-method-PUT, .doc-method-PATCH { background: #d97706; }
        .doc-method-DELETE { background: #dc2626; }

        .doc-article-desc { font-size: 14px; white-space: pre-line; margin-bottom: 14px; }
        .doc-article h5 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); margin: 14px 0 6px; }
        .doc-article pre {
            background: var(--code-bg);
            color: #e2e8f0;
            padding: 14px 16px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 13px;
            margin: 0;
        }
        .doc-empty { color: var(--muted); padding: 40px; text-align: center; }
        .doc-footer { text-align: center; color: var(--muted); font-size: 13px; padding: 24px; }
    </style>
</head>
<body>
    <div class="doc-header">
        <div>
            <h1>Dokumentasi WhatsApp API</h1>
            <div class="doc-header-sub">{{ config('app.name', 'Mirbal') }}</div>
        </div>
        <div class="doc-header-sub">Publik &mdash; tidak perlu login</div>
    </div>

    <div class="doc-layout">
        <aside class="doc-sidebar">
            @forelse ($categories as $category)
                @if ($category->apiDocumentations->isNotEmpty())
                    <h4>{{ $category->name }}</h4>
                    <ul>
                        @foreach ($category->apiDocumentations as $article)
                            <li>
                                <a href="#{{ $article->slug }}">
                                    <span class="doc-method-tag">{{ $article->method }}</span>{{ $article->title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @empty
            @endforelse
        </aside>

        <main class="doc-content">
            <div class="doc-intro">
                Halaman ini menjelaskan cara pihak ketiga (sistem lain) mengirim pesan WhatsApp lewat device yang
                sudah terhubung ke akun Anda &mdash; tanpa perlu login ke dashboard. Token &amp; Secret Key didapat dari
                halaman <strong>Device</strong> pada dashboard, tombol <strong>API Key</strong>.
            </div>

            @forelse ($categories as $category)
                @if ($category->apiDocumentations->isNotEmpty())
                    <section class="doc-category" id="{{ $category->slug }}">
                        <h2>{{ $category->name }}</h2>
                        @if ($category->description)
                            <p>{{ $category->description }}</p>
                        @endif

                        @foreach ($category->apiDocumentations as $article)
                            <article class="doc-article" id="{{ $article->slug }}">
                                <h3>{{ $article->title }}</h3>
                                <div class="doc-endpoint">
                                    <span class="doc-method doc-method-{{ $article->method }}">{{ $article->method }}</span>
                                    <span>{{ $article->endpoint }}</span>
                                </div>

                                @if ($article->description)
                                    <div class="doc-article-desc">{{ $article->description }}</div>
                                @endif

                                @if ($article->request_example)
                                    <h5>Contoh Request</h5>
                                    <pre><code>{{ $article->request_example }}</code></pre>
                                @endif

                                @if ($article->response_example)
                                    <h5>Contoh Response</h5>
                                    <pre><code>{{ $article->response_example }}</code></pre>
                                @endif
                            </article>
                        @endforeach
                    </section>
                @endif
            @empty
                <div class="doc-empty">Dokumentasi belum tersedia.</div>
            @endforelse
        </main>
    </div>

    <div class="doc-footer">&copy; {{ date('Y') }} {{ config('app.name', 'Mirbal') }}</div>
</body>
</html>
