@extends('layouts.app')

@section('title', __('help.title'))

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('help.title') }}</h1>
        <p class="text-sm text-muted mt-2 max-w-2xl">
            {{ __('help.intro') }}
        </p>
    </div>

    {{-- Indice --}}
    <nav class="card p-4 mb-8 text-sm">
        <p class="text-xs uppercase tracking-wider text-muted mb-2">{{ __('help.toc_heading') }}</p>
        <ol class="grid grid-cols-1 sm:grid-cols-2 gap-1">
            <li><a class="underline hover:opacity-80" href="#que-es">1. {{ __('help.toc_1') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#arquitectura">2. {{ __('help.toc_2') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#instalacion">3. {{ __('help.toc_3') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#arranque">4. {{ __('help.toc_4') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#vistas">5. {{ __('help.toc_5') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#editar">6. {{ __('help.toc_6') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#proyectos">7. {{ __('help.toc_7') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#export">8. {{ __('help.toc_8') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#scheduler">9. {{ __('help.toc_9') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#troubleshooting">10. {{ __('help.toc_10') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#privacidad">11. {{ __('help.toc_11') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#notas">12. {{ __('help.toc_12') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#tareas">13. {{ __('help.toc_13') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#inicio">14. {{ __('help.toc_14') }}</a></li>
            <li><a class="underline hover:opacity-80" href="#datos">15. {{ __('help.toc_15') }}</a></li>
        </ol>
    </nav>

    {{-- 1 --}}
    <section id="que-es" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s1_title') }}</h2>
        <p class="text-sm mb-3"><strong>{{ __('help.s1_is_label') }}</strong> {{ __('help.s1_is') }}</p>
        <p class="text-sm"><strong>{{ __('help.s1_isnot_label') }}</strong> {{ __('help.s1_isnot') }}</p>
    </section>

    {{-- 2 --}}
    <section id="arquitectura" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s2_title') }}</h2>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li>{!! __('help.s2_daemon') !!}</li>
            <li>{!! __('help.s2_sqlite', ['path' => $dbPath]) !!}</li>
            <li>{!! __('help.s2_dashboard', ['min' => $blockMin]) !!}</li>
        </ul>
    </section>

    {{-- 3 --}}
    <section id="instalacion" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s3_title') }}</h2>
        <h3 class="text-sm font-semibold mt-3 mb-1">{{ __('help.s3_1_title') }}</h3>
        <pre class="surface-soft text-xs rounded p-3 overflow-x-auto"><code>sudo apt install -y xdotool x11-utils python3.11 python3.11-venv \
    php8.4-cli php8.4-sqlite3 php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-intl composer sqlite3 git</code></pre>

        <h3 class="text-sm font-semibold mt-4 mb-1">{{ __('help.s3_2_title') }}</h3>
        <pre class="surface-soft text-xs rounded p-3 overflow-x-auto"><code>cd dashboard
composer install
cp .env.example .env
php artisan key:generate
mkdir -p ../storage
php artisan migrate --seed</code></pre>

        <h3 class="text-sm font-semibold mt-4 mb-1">{{ __('help.s3_3_title') }}</h3>
        <pre class="surface-soft text-xs rounded p-3 overflow-x-auto"><code># bash/zsh
cd tracker
python3.11 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
pip install -e .
cp config.example.yml config.yml

# fish
source .venv/bin/activate.fish</code></pre>

        <h3 class="text-sm font-semibold mt-4 mb-1">{{ __('help.s3_4_title') }}</h3>
        <pre class="surface-soft text-xs rounded p-3 overflow-x-auto"><code>cd dashboard
npm install
npm run build</code></pre>
    </section>

    {{-- 4 --}}
    <section id="arranque" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s4_title') }}</h2>
        <ol class="text-sm space-y-2 list-decimal pl-5">
            <li>
                <strong>{{ __('help.s4_daemon_label') }}</strong>. {!! __('help.s4_daemon_hint') !!}
                <pre class="surface-soft text-xs rounded p-2 mt-1"><code>systemctl --user enable --now trackactivity.service</code></pre>
                {{ __('help.s4_foreground') }}
                <pre class="surface-soft text-xs rounded p-2 mt-1"><code>tracker run --foreground --log-level=DEBUG</code></pre>
            </li>
            <li>
                <strong>{{ __('help.s4_dash_label') }}</strong>:
                <pre class="surface-soft text-xs rounded p-2 mt-1"><code>cd dashboard
php artisan serve   # http://127.0.0.1:8000</code></pre>
            </li>
            <li>
                <strong>{{ __('help.s4_check_label') }}</strong>:
                <pre class="surface-soft text-xs rounded p-2 mt-1"><code>tracker doctor         # daemon side
php artisan tracker:doctor    # dashboard side</code></pre>
            </li>
        </ol>
    </section>

    {{-- 5 --}}
    <section id="vistas" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s5_title') }}</h2>
        <dl class="text-sm space-y-3">
            <div>
                <dt class="font-semibold"><a class="underline" href="{{ route('timeline.today') }}">{{ __('nav.today') }}</a></dt>
                <dd class="text-muted">{{ __('help.s5_today') }}</dd>
            </div>
            <div>
                <dt class="font-semibold"><a class="underline" href="{{ route('timeline.this_week') }}">{{ __('nav.week') }}</a></dt>
                <dd class="text-muted">{{ __('help.s5_week') }}</dd>
            </div>
            <div>
                <dt class="font-semibold"><a class="underline" href="{{ route('calendar.current') }}">{{ __('nav.month') }}</a></dt>
                <dd class="text-muted">{{ __('help.s5_calendar') }}</dd>
            </div>
            <div>
                <dt class="font-semibold"><a class="underline" href="{{ route('export.form') }}">{{ __('nav.settings_export') }}</a></dt>
                <dd class="text-muted">{{ __('help.s5_export') }}</dd>
            </div>
            <div>
                <dt class="font-semibold"><a class="underline" href="{{ route('projects.index') }}">{{ __('nav.settings_projects') }}</a></dt>
                <dd class="text-muted">{{ __('help.s5_projects') }}</dd>
            </div>
        </dl>
        <p class="text-xs text-muted mt-3">{!! __('help.s5_tz_note', ['tz' => $tz]) !!}</p>
    </section>

    {{-- 6 --}}
    <section id="editar" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s6_title') }}</h2>
        <p class="text-sm mb-3">{!! __('help.s6_intro') !!}</p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li><strong>{{ __('help.s6_project_lbl') }}</strong> — {{ __('help.s6_project') }}</li>
            <li><strong>{{ __('help.s6_summary_lbl') }}</strong> — {{ __('help.s6_summary') }}</li>
        </ul>
        <p class="text-sm mt-3">{!! __('help.s6_saved') !!}</p>
        <p class="text-sm mt-3">{!! __('help.s6_revert') !!}</p>
        <p class="text-xs text-muted mt-3">{!! __('help.s6_force_note') !!}</p>

        <h3 class="text-sm font-semibold mt-5 mb-1">{{ __('help.s6_manual_title') }}</h3>
        <p class="text-sm mb-3">{!! __('help.s6_manual_intro') !!}</p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li>{!! __('help.s6_manual_1') !!}</li>
            <li>{!! __('help.s6_manual_2') !!}</li>
            <li>{!! __('help.s6_manual_3') !!}</li>
            <li>{{ __('help.s6_manual_4') }}</li>
            <li>{!! __('help.s6_manual_5') !!}</li>
        </ul>
    </section>

    {{-- 7 --}}
    <section id="proyectos" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s7_title') }}</h2>
        <p class="text-sm mb-3">{!! __('help.s7_intro') !!}</p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li><code class="chip">repository</code> — {!! __('help.s7_repo') !!}</li>
            <li><code class="chip">folder</code> — {!! __('help.s7_folder') !!}</li>
            <li><code class="chip">url_pattern</code> — {!! __('help.s7_url') !!}</li>
            <li><code class="chip">email_subject</code> — {{ __('help.s7_email') }}</li>
            <li><code class="chip">window_title</code> — {{ __('help.s7_window') }}</li>
        </ul>
        <p class="text-sm mt-3">{!! __('help.s7_matching') !!}</p>
        <p class="text-sm mt-3">{!! __('help.s7_scoring') !!}</p>
        <p class="text-sm mt-3">{!! __('help.s7_rebuild') !!}</p>
        <pre class="surface-soft text-xs rounded p-3 mt-2"><code>php artisan tracker:rebuild-blocks --day=$(date +%F)</code></pre>
    </section>

    {{-- 8 --}}
    <section id="export" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s8_title') }}</h2>
        <p class="text-sm mb-3">{!! __('help.s8_intro', ['url' => route('export.form')]) !!}</p>
        <pre class="surface-soft text-xs rounded p-3 overflow-x-auto"><code>php artisan tracker:export --from=2026-05-13 --to=2026-05-19 \
    --project=JASPER --format=md --min-confidence=medium \
    --output=~/Documents/timesheets/week.md</code></pre>
        <ul class="text-sm space-y-1 list-disc pl-5 mt-3">
            <li>{!! __('help.s8_txt') !!}</li>
            <li>{!! __('help.s8_md') !!}</li>
            <li>{!! __('help.s8_csv') !!}</li>
        </ul>
        <p class="text-sm mt-3">{!! __('help.s8_manual') !!}</p>
        <p class="text-sm mt-3">{!! __('help.s8_groupby') !!}</p>
    </section>

    {{-- 9 --}}
    <section id="scheduler" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s9_title') }}</h2>
        <p class="text-sm mb-3">{{ __('help.s9_intro') }}</p>
        <pre class="surface-soft text-xs rounded p-3"><code>cd dashboard
php artisan schedule:work</code></pre>
        <p class="text-sm mt-3">{{ __('help.s9_schedule') }}</p>
        <p class="text-sm mt-3">{{ __('help.s9_cron') }}</p>
        <pre class="surface-soft text-xs rounded p-3"><code>* * * * * cd /var/www/html/trackActivity/dashboard && \
    php artisan schedule:run >> /dev/null 2>&1</code></pre>
    </section>

    {{-- 10 --}}
    <section id="troubleshooting" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s10_title') }}</h2>
        <dl class="text-sm space-y-3">
            <div>
                <dt class="font-semibold">{{ __('help.s10_q1') }}</dt>
                <dd class="text-muted">{!! __('help.s10_a1') !!}</dd>
            </div>
            <div>
                <dt class="font-semibold">{{ __('help.s10_q2') }}</dt>
                <dd class="text-muted">{!! __('help.s10_a2', ['url' => route('projects.index')]) !!}</dd>
            </div>
            <div>
                <dt class="font-semibold">{{ __('help.s10_q3') }}</dt>
                <dd class="text-muted">{!! __('help.s10_a3') !!}</dd>
            </div>
            <div>
                <dt class="font-semibold">{{ __('help.s10_q4') }}</dt>
                <dd class="text-muted">{!! __('help.s10_a4') !!}</dd>
            </div>
            <div>
                <dt class="font-semibold">{{ __('help.s10_q5') }}</dt>
                <dd class="text-muted">{{ __('help.s10_a5') }}</dd>
            </div>
            <div>
                <dt class="font-semibold">{!! __('help.s10_q6') !!}</dt>
                <dd class="text-muted">{!! __('help.s10_a6') !!}</dd>
            </div>
        </dl>
    </section>

    {{-- 11 --}}
    <section id="privacidad" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s11_title') }}</h2>
        <p class="text-sm">{!! __('help.s11_local') !!}</p>
        <p class="text-sm mt-2">{!! __('help.s11_prune') !!}</p>
    </section>

    {{-- 12 --}}
    <section id="notas" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s12_title') }}</h2>
        <p class="text-sm mb-3">{!! __('help.s12_intro', ['url' => route('notes.index')]) !!}</p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li>{!! __('help.s12_folders') !!}</li>
            <li>{!! __('help.s12_editor') !!}</li>
            <li>{!! __('help.s12_search') !!}</li>
            <li>{!! __('help.s12_pin') !!}</li>
        </ul>
    </section>

    {{-- 13 --}}
    <section id="tareas" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s13_title') }}</h2>
        <p class="text-sm mb-3">{!! __('help.s13_intro', ['url' => route('tasks.index')]) !!}</p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li>{!! __('help.s13_modal') !!}</li>
            <li>{!! __('help.s13_drag') !!}</li>
            <li>{{ __('help.s13_card') }}</li>
            <li>{{ __('help.s13_filter') }}</li>
            <li>{!! __('help.s13_wip') !!}</li>
        </ul>
    </section>

    {{-- 14 --}}
    <section id="inicio" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s14_title') }}</h2>
        <p class="text-sm mb-3">{!! __('help.s14_intro', ['url' => route('dashboard')]) !!}</p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li>{!! __('help.s14_week') !!}</li>
            <li>{!! __('help.s14_heatmap') !!}</li>
            <li>{!! __('help.s14_now') !!}</li>
            <li>{{ __('help.s14_recent') }}</li>
            <li>{{ __('help.s14_alert') }}</li>
        </ul>
        <p class="text-sm mt-3">
            {!! __('help.s14_palette') !!}
        </p>
    </section>

    {{-- 15 --}}
    <section id="datos" class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">{{ __('help.s15_title') }}</h2>
        <p class="text-sm mb-3">{!! __('help.s15_intro', ['url' => route('data.index')]) !!}</p>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li>{!! __('help.s15_backup') !!}</li>
            <li>{!! __('help.s15_restore') !!}</li>
            <li>{!! __('help.s15_export') !!}</li>
        </ul>
    </section>
@endsection
