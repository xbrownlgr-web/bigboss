@extends('admin.layout')

@section('title', 'Vidéos tutoriels')
@section('subtitle', 'Vidéos verticales (9:16) affichées dans l’application pour guider les utilisateurs')

@push('head')
    <style>
        .bf-phone {
            position: relative;
            width: 100%;
            max-width: 240px;
            aspect-ratio: 9 / 16;
            margin: 0 auto;
            overflow: hidden;
            border: 6px solid #111A2E;
            border-radius: 26px;
            background: #0E1A36;
            box-shadow: 0 14px 30px rgba(16, 24, 40, .18);
        }

        .bf-phone video { width: 100%; height: 100%; object-fit: cover; background: #000; }
        .bf-phone .bf-empty { display: flex; flex-direction: column; justify-content: center; height: 100%; color: #8C98B5; }
        .bf-progress { height: 8px; border-radius: 999px; background: var(--bf-surface-2); overflow: hidden; }
        .bf-progress > div { height: 100%; width: 0; border-radius: 999px; background: var(--bf-navy); transition: width .2s; }
    </style>
@endpush

@section('content')
    @unless ($cloudinaryReady)
        <div class="bf-flash warning">
            <i class="bi bi-cloud-arrow-up"></i>
            <div class="flex-grow-1">
                Pour importer des vidéos depuis votre ordinateur ou téléphone, créez un compte gratuit sur
                <strong>cloudinary.com</strong> puis renseignez <em>Cloud name</em>, <em>API Key</em> et <em>API Secret</em>
                dans les paramètres (section Vidéos). En attendant, vous pouvez coller un lien direct vers une vidéo MP4.
            </div>
            <a href="/admin/settings#videos" class="btn btn-sm bf-btn-light flex-shrink-0">Paramètres</a>
        </div>
    @endunless

    <div class="row g-3">
        @foreach ($slots as $key => $slot)
            <div class="col-12 col-xl-6">
                <section class="bf-card h-100" data-slot="{{ $key }}">
                    <div class="bf-card-header">
                        <div>
                            <h2 class="bf-card-title">{{ $slot['title'] }}</h2>
                            <p class="bf-card-sub">{{ $slot['description'] }}</p>
                        </div>
                        @if ($slot['url'])
                            <span class="bf-badge success"><span class="dot"></span>En ligne</span>
                        @else
                            <span class="bf-badge neutral">Aucune vidéo</span>
                        @endif
                    </div>
                    <div class="bf-card-body">
                        <div class="row g-4 align-items-center">
                            <div class="col-12 col-sm-5">
                                <div class="bf-phone">
                                    @if ($slot['url'])
                                        <video src="{{ $slot['url'] }}" controls playsinline preload="metadata"></video>
                                    @else
                                        <div class="bf-empty"><i class="bi bi-camera-video"></i>Format vertical 9:16</div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-12 col-sm-7 vstack gap-3">
                                <div>
                                    <label class="form-label">Importer une vidéo</label>
                                    <input class="form-control" type="file" accept="video/mp4,video/quicktime,video/*" data-file @disabled(! $cloudinaryReady)>
                                    <div class="form-text">MP4 ou MOV, vertical (9:16), 100 Mo maximum. 30 à 90 secondes, c’est l’idéal.</div>
                                    <div class="mt-2" data-progress-wrap hidden>
                                        <div class="bf-progress"><div data-progress></div></div>
                                        <div class="bf-small bf-muted mt-1" data-status>Envoi…</div>
                                    </div>
                                </div>

                                <form method="POST" action="/admin/videos/{{ $key }}" data-save-form>
                                    @csrf
                                    <label class="form-label" for="url-{{ $key }}">Ou coller un lien direct</label>
                                    <div class="input-group">
                                        <input class="form-control" id="url-{{ $key }}" type="url" name="url" value="{{ $slot['url'] }}" placeholder="https://…/video.mp4" required>
                                        <button class="btn bf-btn-primary" type="submit">Enregistrer</button>
                                    </div>
                                </form>

                                @if ($slot['url'])
                                    <form method="POST" action="/admin/videos/{{ $key }}/delete" data-confirm="Retirer cette vidéo de l’application ?">
                                        @csrf
                                        <button class="btn btn-sm bf-btn-danger-soft" type="submit"><i class="bi bi-trash"></i> Retirer la vidéo</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script>
        // Envoi direct navigateur → Cloudinary (signé par le serveur), puis enregistrement du lien.
        document.querySelectorAll('[data-slot]').forEach(function (card) {
            const input = card.querySelector('[data-file]');
            const wrap = card.querySelector('[data-progress-wrap]');
            const bar = card.querySelector('[data-progress]');
            const status = card.querySelector('[data-status]');
            const form = card.querySelector('[data-save-form]');

            input?.addEventListener('change', async function () {
                const file = input.files[0];
                if (! file) return;

                if (file.size > 100 * 1024 * 1024) {
                    alert('Vidéo trop lourde : 100 Mo maximum.');
                    input.value = '';
                    return;
                }

                input.disabled = true;
                wrap.hidden = false;
                status.textContent = 'Préparation…';

                try {
                    const response = await fetch('/admin/videos/signature', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    });
                    const signed = await response.json();

                    if (! response.ok) throw new Error(signed.message || 'Signature refusée');

                    const data = new FormData();
                    Object.entries(signed.params).forEach(([key, value]) => data.append(key, value));
                    data.append('file', file);

                    const result = await new Promise(function (resolve, reject) {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', signed.upload_url);
                        xhr.upload.onprogress = function (event) {
                            if (event.lengthComputable) {
                                const percent = Math.round(event.loaded * 100 / event.total);
                                bar.style.width = percent + '%';
                                status.textContent = 'Envoi… ' + percent + ' %';
                            }
                        };
                        xhr.onload = function () {
                            const body = JSON.parse(xhr.responseText || '{}');
                            xhr.status < 300 ? resolve(body) : reject(new Error(body.error?.message || 'Envoi refusé'));
                        };
                        xhr.onerror = function () { reject(new Error('Connexion interrompue')); };
                        xhr.send(data);
                    });

                    // Lien de lecture converti en MP4 H.264, lisible par tous les téléphones.
                    const playbackUrl = result.secure_url
                        .replace('/video/upload/', '/video/upload/' + signed.transformation + '/')
                        .replace(/\.[a-z0-9]+$/i, '.mp4');

                    status.textContent = 'Envoyée. Enregistrement…';
                    form.querySelector('input[name="url"]').value = playbackUrl;
                    form.submit();
                } catch (error) {
                    status.textContent = 'Échec : ' + error.message;
                    bar.style.width = '0';
                    input.disabled = false;
                }
            });
        });
    </script>
@endpush
