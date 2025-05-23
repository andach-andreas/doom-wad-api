<nav class="mt-6">
    <div>
        <div>
            <h2 class="text-ui-primary text-lg font-bold select-none">Browse</h2>
        </div>
        <ul class="pl-4 text-ui-secondary">
            <li>
                <a href="{{ route('wad.index') }}" class="hover:opacity-75 ">Wads</a>
            </li>
        </ul>
    </div>
</nav>

<nav class="mt-6">
    <div>
        <div>
            <h2 class="text-ui-primary text-lg font-bold select-none">Endpoints</h2>
        </div>
        <ul class="pl-4 text-ui-secondary">
            <li>
                <a href="{{ route('docs.map') }}" class="hover:opacity-75 ">Map</a>
            </li>
            <li>
                <a href="{{ route('docs.wad') }}" class="hover:opacity-75 ">Wad</a>
            </li>
        </ul>
    </div>
</nav>
