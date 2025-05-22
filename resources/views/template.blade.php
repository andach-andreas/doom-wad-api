<!DOCTYPE html>
<html lang="en">
<head>
    <title>Recent templates - TailwindTemplates</title><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link href="https://api.fontshare.com/v2/css?f[]=supreme@501,2,800,400,401,200,100,300,101,301,500,201,801,1,701,700&f[]=technor@300,1,600,200,400,700,500,900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        if (localStorage.getItem('dark-mode') === 'false' || !('dark-mode' in localStorage)) {
            document.querySelector('html').classList.remove('dark');
            document.querySelector('html').style.colorScheme = 'light';
        } else {
            document.querySelector('html').classList.add('dark');
            document.querySelector('html').style.colorScheme = 'dark';
        }
    </script>
    @yield('head')
</head>
<body class="font-inter antialiased bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400">

<!-- Mobile nav -->
<div x-data="{open: false}" class="py-6 px-4 bg-slate-100 text-slate-600 lg:hidden">
    <div class="flex items-center justify-between">
        @include('title')
        <button @click="open = !open">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <div x-show="open" style="display: none;">
        @include('nav')
    </div>
</div>

<!-- Desktop nav -->
<div class="grid grid-cols-1 lg:grid-cols-4 h-screen">
    <div class="hidden lg:block bg-slate-100 text-slate-600 w-64 p-8 h-auto overflow-y-auto custom-scrollbar col-span-1 pb-32 relative">
        @include('title')

        @include('nav')

        <div class="border-t border-gray-100 px-8 py-4 fixed bottom-0 w-64 -ml-8 text-sm flex flex-wrap ">
            <a href="{{ route('home') }}" class="mr-2 hover:text-secondary px-2 py-1 rounded-full">Home</a>
        </div>
    </div>
    <div class="w-full max-w-screen-xl col-span-3">
        <div class="px-3 md:px-8 lg:pr-24 pt-8 pb-12">
            <h1 class="font-bold text-4xl text-primary">
                @yield('title', 'Andach Doom')
            </h1>

            <div class="mt-12">
                @yield('content')
            </div>
        </div>
    </div>
</div>
</body>
</html>
