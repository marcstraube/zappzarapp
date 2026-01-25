<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Development Dashboard') ?> | zappzarapp</title>
    <link rel="icon" type="image/svg+xml" href="/assets/dev-dashboard/favicon.svg">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f9fafb; color: #111827; line-height: 1.5; }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 1rem; }

        /* Sticky Top Bar */
        .sticky-top { position: sticky; top: 0; z-index: 100; background: white; }

        /* Header */
        header { background: white; border-bottom: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        header .container { display: flex; justify-content: space-between; align-items: center; padding: 1rem; }
        h1 { font-size: 1.5rem; font-weight: 700; color: #111827; }
        .env-badge { display: inline-block; margin-left: 0.75rem; padding: 0.25rem 0.5rem; font-size: 0.75rem; font-weight: 600; background: #dbeafe; color: #1e40af; border-radius: 0.25rem; }

        /* Navigation */
        nav { background: white; border-bottom: 1px solid #e5e7eb; }
        nav .container { display: flex; gap: 2rem; }
        nav a { display: inline-block; padding: 1rem 0.25rem; border-bottom: 2px solid transparent; color: #6b7280; font-size: 0.875rem; font-weight: 500; text-decoration: none; transition: all 0.2s; }
        nav a:hover { color: #374151; border-color: #d1d5db; }
        nav a.active { color: #2563eb; border-color: #2563eb; }

        /* Main */
        main { padding: 2rem 0; }

        /* Cards */
        .card { background: white; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.5rem; margin-bottom: 1.5rem; }
        .card h2, .card h3 { font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; }

        /* Grid */
        .grid { display: grid; gap: 1.5rem; }
        .grid-cols-1 { grid-template-columns: repeat(1, 1fr); }
        .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
        @media (min-width: 768px) {
            .md\\:grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
            .md\\:grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
        }
        @media (min-width: 1024px) {
            .lg\\:grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
            .lg\\:grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
        }
        .col-span-full { grid-column: 1 / -1; }

        /* Badges */
        .badge { display: inline-block; padding: 0.25rem 0.5rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.25rem; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .badge-blue { background: #dbeafe; color: #1e40af; }

        /* Buttons */
        .btn { display: inline-block; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; border-radius: 0.375rem; transition: all 0.2s; border: 1px solid; cursor: pointer; }
        .btn-primary { background: #2563eb; color: white; border-color: #2563eb; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
        .btn-secondary:hover { background: #dbeafe; }
        .btn-block { display: block; width: 100%; text-align: center; }

        /* Utilities */
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .text-gray-500 { color: #6b7280; }
        .text-gray-700 { color: #374151; }
        .text-gray-900 { color: #111827; }
        .text-green-600 { color: #059669; }
        .text-red-600 { color: #dc2626; }
        .text-blue-600 { color: #2563eb; }
        .font-mono { font-family: ui-monospace, monospace; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-3 { margin-bottom: 0.75rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mt-4 { margin-top: 1rem; }
        .mt-8 { margin-top: 2rem; }
        .space-y-2 > * + * { margin-top: 0.5rem; }
        .space-y-4 > * + * { margin-top: 1rem; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .justify-center { justify-content: center; }
        .gap-4 { gap: 1rem; }
        .border { border: 1px solid #e5e7eb; }
        .border-t { border-top: 1px solid #e5e7eb; }
        .rounded { border-radius: 0.25rem; }
        .rounded-lg { border-radius: 0.5rem; }
        .rounded-full { border-radius: 9999px; }
        .p-2 { padding: 0.5rem; }
        .p-4 { padding: 1rem; }
        .p-6 { padding: 1.5rem; }
        .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        .bg-gray-50 { background: #f9fafb; }
        .bg-blue-50 { background: #eff6ff; }
        .bg-green-50 { background: #f0fdf4; }
        .bg-red-50 { background: #fef2f2; }
        .bg-yellow-50 { background: #fefce8; }
        .text-center { text-align: center; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; }
        table th { text-align: left; padding: 0.75rem 1rem; background: #f9fafb; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; }
        table td { padding: 0.5rem 1rem; border-bottom: 1px solid #e5e7eb; font-size: 0.875rem; }
        table tr:hover { background: #f9fafb; }

        /* Footer */
        footer { background: white; border-top: 1px solid #e5e7eb; margin-top: 3rem; padding: 1rem 0; text-align: center; font-size: 0.875rem; color: #6b7280; }
        footer a { color: #2563eb; text-decoration: none; }
        footer a:hover { text-decoration: underline; }

        /* Info Box */
        .info-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; padding: 1.5rem; }
        .info-box h3 { color: #1e3a8a; font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; }
        .info-box p { color: #1e40af; font-size: 0.875rem; }

        /* Additional Utilities */
        .space-x-2 > * + * { margin-left: 0.5rem; }
        .space-x-4 > * + * { margin-left: 1rem; }
        .gap-2 { gap: 0.5rem; }
        .gap-6 { gap: 1.5rem; }
        .mx-auto { margin-left: auto; margin-right: auto; }
        .ml-auto { margin-left: auto; }
        .max-w-lg { max-width: 32rem; }
        .max-w-2xl { max-width: 42rem; }
        .w-24 { width: 6rem; }
        .h-12 { height: 3rem; }
        .w-12 { width: 3rem; }
        .h-24 { height: 6rem; }
        .capitalize { text-transform: capitalize; }
        .uppercase { text-transform: uppercase; }
        .tracking-wider { letter-spacing: 0.05em; }
        .divide-y > * + * { border-top: 1px solid #e5e7eb; }
        .divide-gray-200 > * + * { border-top-color: #e5e7eb; }
        .min-w-full { min-width: 100%; }
        .overflow-hidden { overflow: hidden; }
        .overflow-y-auto { overflow-y: auto; }
        .flex-shrink-0 { flex-shrink: 0; }
        .inline-flex { display: inline-flex; }
        .w-full { width: 100%; }
        .block { display: block; }
        .transition { transition: all 0.2s; }
        .hover\:bg-blue-700:hover { background: #1d4ed8; }
        .hover\:bg-blue-100:hover { background: #dbeafe; }
        .hover\:bg-gray-50:hover { background: #f9fafb; }
        .hover\:text-blue-800:hover { color: #1e40af; }
        .hover\:bg-green-700:hover { background: #15803d; }
        .hover\:bg-purple-700:hover { background: #7e22ce; }
        .text-purple-600 { color: #9333ea; }
        .text-purple-800 { color: #6b21a8; }
        .text-purple-900 { color: #581c87; }
        .text-green-800 { color: #166534; }
        .text-green-900 { color: #14532d; }
        .text-yellow-700 { color: #a16207; }
        .text-yellow-800 { color: #854d0e; }
        .text-blue-700 { color: #1d4ed8; }
        .text-blue-800 { color: #1e40af; }
        .text-blue-900 { color: #1e3a8a; }
        .bg-purple-50 { background: #faf5ff; }
        .bg-purple-100 { background: #f3e8ff; }
        .bg-purple-600 { background: #9333ea; }
        .bg-green-100 { background: #dcfce7; }
        .bg-green-600 { background: #16a34a; }
        .bg-yellow-100 { background: #fef9c3; }
        .bg-yellow-200 { background: #fef08a; }
        .bg-blue-100 { background: #dbeafe; }
        .bg-blue-600 { background: #2563eb; }
        .bg-gray-100 { background: #f3f4f6; }
        .border-blue-200 { border-color: #bfdbfe; }
        .border-blue-300 { border-color: #93c5fd; }
        .border-purple-200 { border-color: #e9d5ff; }
        .border-green-200 { border-color: #bbf7d0; }
        .border-yellow-200 { border-color: #fef08a; }
        .border-gray-200 { border-color: #e5e7eb; }
        .border-gray-300 { border-color: #d1d5db; }
        .text-white { color: white; }
        .text-2xl { font-size: 1.5rem; }
        .text-5xl { font-size: 3rem; }
        .pl-4 { padding-left: 1rem; }
        .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .p-12 { padding: 3rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .mt-8 { margin-top: 2rem; }

        /* Additional Layout Utilities */
        .space-y-6 > * + * { margin-top: 1.5rem; }
        .space-y-3 > * + * { margin-top: 0.75rem; }
        .space-y-1 > * + * { margin-top: 0.25rem; }
        .text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
        .flex-wrap { flex-wrap: wrap; }
        .opacity-75 { opacity: 0.75; }
        .p-3 { padding: 0.75rem; }
        .px-2 { padding-left: 0.5rem; padding-right: 0.5rem; }
        .px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
        .py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; }
        .mb-1 { margin-bottom: 0.25rem; }
        .mt-3 { margin-top: 0.75rem; }
        .text-gray-400 { color: #9ca3af; }
        .text-gray-600 { color: #4b5563; }
        .border-l { border-left: 1px solid #e5e7eb; }
        .overflow-x-auto { overflow-x: auto; }
        .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .max-w-\[200px\] { max-width: 200px; }
        .space-y-1\.5 > * + * { margin-top: 0.375rem; }
        .text-blue-300 { color: #93c5fd; }
        .text-yellow-600 { color: #ca8a04; }
        .text-green-700 { color: #15803d; }
        .bg-white { background: white; }
        @media (min-width: 768px) {
            .md\:grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
        }

        /* Modal Utilities */
        .hidden { display: none !important; }
        .fixed { position: fixed; }
        .absolute { position: absolute; }
        .relative { position: relative; }
        .inset-0 { top: 0; right: 0; bottom: 0; left: 0; }
        .z-50 { z-index: 50; }
        .overflow-y-auto { overflow-y: auto; }
        .min-h-screen { min-height: 100vh; }
        .bg-opacity-75 { opacity: 0.75; }
        .bg-gray-500 { background: #6b7280; }
        .bg-gray-900 { background: #111827; }
        .shadow-xl { box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .transform { transform: translateZ(0); }
        .sm\:max-w-5xl { max-width: 64rem; }
        .sm\:w-full { width: 100%; }
        .mx-4 { margin-left: 1rem; margin-right: 1rem; }
        .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
        .py-4 { padding-top: 1rem; padding-bottom: 1rem; }
        .border-b { border-bottom: 1px solid #e5e7eb; }
        .rounded-t-lg { border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; }
        .rounded-b-lg { border-bottom-left-radius: 0.5rem; border-bottom-right-radius: 0.5rem; }
        .gap-3 { gap: 0.75rem; }
        .text-lg { font-size: 1.125rem; }
        .mt-1 { margin-top: 0.25rem; }
        .w-5 { width: 1.25rem; }
        .h-5 { height: 1.25rem; }
        .w-6 { width: 1.5rem; }
        .h-6 { height: 1.5rem; }
        .max-h-\[70vh\] { max-height: 70vh; }
        .whitespace-pre-wrap { white-space: pre-wrap; }
        .break-words { word-break: break-word; }
        .text-green-400 { color: #4ade80; }
        .text-red-400 { color: #f87171; }
        .px-1\.5 { padding-left: 0.375rem; padding-right: 0.375rem; }
        .py-0\.5 { padding-top: 0.125rem; padding-bottom: 0.125rem; }
        .bg-gray-200 { background: #e5e7eb; }
        .focus\:outline-none:focus { outline: none; }
        .focus\:ring-2:focus { box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5); }
        .cursor-pointer { cursor: pointer; }
        .text-left { text-align: left; }
        .hover\:text-gray-700:hover { color: #374151; }
        .hover\:underline:hover { text-decoration: underline; }
        select { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; }
        select:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }
    </style>
</head>
<body>
    <!-- Sticky Top Bar -->
    <div class="sticky-top">
        <!-- Header -->
        <header>
            <div class="container">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <h1>⚡ zappzarapp</h1>
                        <span class="env-badge">Development Dashboard</span>
                    </div>
                    <div class="text-sm text-gray-500">
                        <?= date('Y-m-d H:i:s') ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Navigation -->
        <nav>
            <div class="container">
                <a href="/_dev" class="<?= ($_SERVER['REQUEST_URI'] ?? '') === '/_dev' || ($_SERVER['REQUEST_URI'] ?? '') === '/_dev/' ? 'active' : '' ?>">
                    📊 Dashboard
                </a>
                <a href="/_dev/system" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/_dev/system') ? 'active' : '' ?>">
                    💻 System
                </a>
                <a href="/_dev/health" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/_dev/health') ? 'active' : '' ?>">
                    🏥 Health
                </a>
                <a href="/_dev/quality" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/_dev/quality') ? 'active' : '' ?>">
                    ✅ Quality
                </a>
                <a href="/_dev/database" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/_dev/database') ? 'active' : '' ?>">
                    💾 Database
                </a>
                <a href="/_dev/logs" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/_dev/logs') ? 'active' : '' ?>">
                    📝 Logs
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <main>
        <div class="container">
            <?= $content ?? '' ?>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>
                ⚡ zappzarapp Development Dashboard • PHP <?= PHP_VERSION ?> •
                <a href="/docs/">Documentation</a> •
                <a href="/">Home</a> •
                <a href="https://github.com/marcstraube/zappzarapp" target="_blank" rel="noopener">GitHub</a>
            </p>
        </div>
    </footer>
</body>
</html>
