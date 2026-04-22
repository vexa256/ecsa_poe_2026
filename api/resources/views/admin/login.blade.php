{{-- /admin/login — shadcn single-card sign-in. POSTs to /api/v2/auth/login,
     stores bearer + user in localStorage, honours 2FA challenges, redirects
     to ?next=… or /admin/dashboard. --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · {{ config('app.name', 'PHEOC Uganda') }}</title>
    @include('admin.partials.theme')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-full bg-background text-foreground antialiased" x-cloak
      x-data="signInPage()" x-init="init()">

<main class="min-h-screen grid place-items-center p-4 sm:p-6">
    <div class="w-full max-w-sm space-y-6">

        {{-- Brand --}}
        <div class="text-center space-y-2">
            <div class="inline-flex h-9 w-9 rounded-md bg-primary text-primary-foreground items-center justify-center">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div>
                <h1 class="text-lg font-semibold tracking-tight">PHEOC Uganda</h1>
                <p class="text-xs text-muted-foreground">Command Centre</p>
            </div>
        </div>

        {{-- Card --}}
        <div class="card">
            <div class="card-header">
                <h2 class="card-title text-base" x-text="challengeId ? 'Two-step sign-in' : 'Sign in'"></h2>
                <p class="card-description" x-text="challengeId ? 'Enter the code from your authenticator app to continue.' : 'Enter your work email and password to continue.'"></p>
            </div>

            <div class="card-content space-y-4">
                {{-- Error banner --}}
                <div x-show="err" class="alert alert-destructive">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <h4 class="alert-title">We couldn't sign you in</h4>
                        <div class="alert-description" x-text="err"></div>
                    </div>
                </div>

                <form @submit.prevent="submit()" class="space-y-3">
                    {{-- Email / password (hidden during 2FA step) --}}
                    <template x-if="!challengeId">
                        <div class="space-y-3">
                            <div class="space-y-1.5">
                                <label class="label" for="li-email">Work email or username</label>
                                <input id="li-email" class="input" type="text" required autofocus autocomplete="username"
                                       maxlength="190" x-model="form.login" placeholder="you@health.go.ug">
                            </div>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="label" for="li-pass">Password</label>
                                    <button type="button" class="btn btn-link btn-xs px-0 h-auto"
                                            @click="err='Ask an administrator to send you a reset link.'">
                                        Forgot?
                                    </button>
                                </div>
                                <input id="li-pass" class="input" type="password" required autocomplete="current-password"
                                       maxlength="255" x-model="form.password">
                            </div>
                        </div>
                    </template>

                    {{-- 2FA code (only shown after challenge issued) --}}
                    <template x-if="challengeId">
                        <div class="space-y-1.5">
                            <label class="label" for="li-code">Six-digit code</label>
                            <input id="li-code" class="input font-mono tracking-widest text-center" type="text"
                                   inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" autofocus
                                   maxlength="10" x-model="form.code" placeholder="••••••">
                            <p class="help-text">Open your authenticator app and enter the current code.</p>
                        </div>
                    </template>

                    <button type="submit" class="btn btn-default w-full" :disabled="loading || !valid">
                        <svg x-show="loading" x-cloak class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                        </svg>
                        <span x-text="challengeId ? 'Verify code' : 'Sign in'"></span>
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-[11px] text-muted-foreground">
            Secured by WHO IHR 2005 &middot; your session expires after 30 days
        </p>
    </div>
</main>

<script>
function signInPage(){
    return {
        form: { login: '', password: '', code: '' },
        challengeId: null,
        loading: false,
        err: '',
        next: new URLSearchParams(location.search).get('next') || '{{ url('/admin/dashboard') }}',

        init(){
            // Already signed in? skip straight through.
            if (localStorage.getItem('pheoc_token')) location.replace(this.next);
        },
        get valid(){
            if (this.challengeId) return (this.form.code || '').length >= 4;
            return (this.form.login || '').length > 1 && (this.form.password || '').length >= 1;
        },
        async _post(path, body){
            return fetch('{{ url('/api/v2/auth') }}' + path, {
                method: 'POST',
                headers: {
                    'Accept':'application/json',
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                },
                body: JSON.stringify(body),
            }).then(async r => ({ status: r.status, body: await r.json().catch(()=>({ok:false,error:'Unexpected server response'})) }));
        },
        async submit(){
            this.err = '';
            this.loading = true;
            try {
                if (this.challengeId) return await this.verify2fa();
                const { status, body } = await this._post('/login', {
                    login: this.form.login.trim(),
                    password: this.form.password,
                });
                if (status === 200 && body.ok && body.data?.challenge_required) {
                    this.challengeId = body.data.challenge_id;
                    return;
                }
                if (status === 200 && body.ok && body.data?.token) {
                    this._persist(body.data);
                    location.replace(this.next);
                    return;
                }
                this.err = body?.error || 'Check your email and password, then try again.';
            } catch (e) {
                this.err = 'Network problem — try once more.';
            } finally {
                this.loading = false;
            }
        },
        async verify2fa(){
            const { status, body } = await this._post('/2fa-verify', {
                challenge_id: this.challengeId,
                code: this.form.code.trim(),
            });
            if (status === 200 && body.ok && body.data?.token) {
                this._persist(body.data);
                location.replace(this.next);
                return;
            }
            this.err = body?.error || 'That code didn\'t match. Try the next one from your app.';
        },
        _persist(data){
            localStorage.setItem('pheoc_token', data.token);
            localStorage.setItem('pheoc_user', JSON.stringify(data.user || {}));
        },
    };
}
</script>
</body>
</html>
