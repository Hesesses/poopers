<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join a League on Poopers</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- OG Meta Tags -->
    <meta property="og:title" content="You've been invited to Poopers!">
    <meta property="og:description" content="Use code {{ $code }} to join a league. Compete with friends. Walk more. Talk trash.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/join/' . $code) }}">
</head>
<body class="bg-amber-50 text-gray-900">
    <section class="min-h-screen flex flex-col items-center justify-center text-center px-6">
        <h1 class="text-5xl font-bold mb-2">💩 Poopers</h1>
        <p class="text-lg text-gray-600 mb-10">You've been invited to a league!</p>

        <div class="bg-white rounded-2xl shadow-sm p-8 max-w-sm w-full mb-8">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-semibold mb-3">Invite Code</p>
            <p class="text-4xl font-bold tracking-widest font-mono">{{ $code }}</p>
        </div>

        <a href="poopers://join/{{ $code }}" class="bg-gray-900 text-white px-8 py-4 rounded-xl text-lg font-semibold hover:bg-gray-800 transition w-full max-w-sm block mb-4">
            Open in Poopers
        </a>

        <a href="https://apps.apple.com/app/poopers/id6744457960" class="bg-white text-gray-900 border border-gray-300 px-8 py-4 rounded-xl text-lg font-semibold hover:bg-gray-100 transition w-full max-w-sm block">
            Download on the App Store
        </a>
    </section>

    <footer class="py-8 px-6 text-gray-500 text-center text-sm">
        <div class="space-x-6">
            <a href="/privacy" class="hover:text-gray-700">Privacy Policy</a>
            <a href="/terms" class="hover:text-gray-700">Terms of Service</a>
        </div>
        <p class="mt-4">&copy; {{ date('Y') }} Poopers. All rights reserved.</p>
    </footer>
</body>
</html>
