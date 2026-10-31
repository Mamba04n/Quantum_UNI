@php
    $scriptBasePath = rtrim(str_replace('\\', '/', dirname(request()->getScriptName())), '/');
    $scriptBasePath = in_array($scriptBasePath, ['.', '/'], true) ? '' : $scriptBasePath;
@endphp

@if($scriptBasePath !== '')
    <script>
        (() => {
            const basePath = @json($scriptBasePath);
            const updateScript = document.querySelector('script[data-update-uri]');

            if (updateScript) {
                const updateUri = updateScript.getAttribute('data-update-uri') || '';

                if (updateUri.startsWith('/livewire/')) {
                    updateScript.setAttribute('data-update-uri', `${basePath}${updateUri}`);
                }
            }

            if (window.livewireScriptConfig?.uri?.startsWith('/livewire/')) {
                window.livewireScriptConfig.uri = `${basePath}${window.livewireScriptConfig.uri}`;
            }
        })();
    </script>
@endif
