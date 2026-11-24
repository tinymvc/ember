# FireLine: Blade Navigation, Forms, Partials, and Reactive Components

> **Read when:** setting up FireLine, writing page/form actions, partials, reactive components, or debugging navigation/419 errors.
> **Related:** [Frontend setup](frontend-setup.md) (Vite) · [Blade views](views-frontend.md) (component syntax) · [Application patterns](application-patterns.md)
> Describes the **FireLine JS 2.1.3** client with the `tinymvc/fireline-php` adapter. Verify the target app's Composer/npm locks and the adapter/client source before using version-specific features. FireLine is an Alpine plugin; it does **not** bundle Alpine.

Contents:

- [Registration and client startup](#registration-and-client-startup)
- [Full document versus page fragment](#full-document-versus-page-fragment)
- [Forms and write responses](#forms-and-write-responses)
- [Reactive components](#reactive-components)
- [Partials, preloads, polling, and state](#partials-preloads-polling-and-state)
- [Headers, deployment, and verification](#headers-deployment-and-verification)

## Registration and client startup

For a requested new integration, add compatible `tinymvc/fireline-php`, `fireline`, and `alpinejs` packages (the client needs Alpine 3.15+ within 3.x). Preserve the app's existing dependencies and providers.

**Server**

- Register `Spark\Fire\FireServiceProvider::class` in `bootstrap/providers.php`.
- Queue `Spark\Fire\FireMiddleware::class` **before** the downstream handlers whose responses/validation exceptions it must handle. Keep CSRF middleware enabled and preserve the rest of the `withMiddleware(...)` config.
- The provider supplies `Route::fire('/about', 'pages/about', $props)` for GET pages, plus `fire()` and `is_fire_js()`. A controller can return `fire('pages/about', $props)` or `Spark\Fire\Fire::render(...)`.
- **Removed core APIs, do not use:** `fireline()`, `Router::fireline()`, `Request::isFirelineRequest()`.

**Client**: register the plugin before the app's single Alpine startup. **Always set `csrfToken` globally**: reactive component requests do not serialize hidden `@csrf` fields, so without it every component sync/action fails with `419 Page Expired`.

```javascript
import './app.css';
import Alpine from 'alpinejs';
import FireLine from 'fireline';

Alpine.plugin(FireLine);
Object.assign(window.FireLine.settings, {
    targetEl: '#app > div',
    focusOnError: true,
    csrfToken: document.querySelector('meta[name="_token"]')?.getAttribute('content'),
});
window.Alpine = Alpine;
Alpine.start();
```

The layout `<head>` must provide the token (use the app's meta name consistently in both places):

```blade
<meta name="_token" content="{{ csrf_token() }}">
```

`csrfToken` is sent as `X-CSRF-TOKEN` on every FireLine request (pages, forms, partials, components). Keep `@csrf` in forms too.

## Full document versus page fragment

The adapter renders the template you give it; it does **not** extract a fragment from a full HTML document. With the `targetEl` above, the layout contains `<div id="app">@yield('content')</div>` and loads `@vite('app.js')` outside that target. The page template supplies one inner `<div>`:

```blade
{{-- resources/views/pages/about.blade.php --}}
@if (!is_fire_js())
    @extends('layouts/app')
@endif

@section('title', 'About us')

@section('content')
<div>
    <h1>{{ $message }}</h1>
    <a x-navigate href="/">Home</a>
</div>
@endsection

@if (is_fire_js())
    @yield('content')
@endif
```

- The layout should render `<title>@yield('title')</title>`. The adapter reads that section or an explicit `title:` argument.
- Navigation replaces the target element itself, so a page fragment must have **exactly one element root** that continues to match `targetEl`. Full documents, empty fragments, and multiple roots are invalid.
- Use `x-navigate` on links. Native, external, download, and modified-click links keep browser behavior; automatic link/form interception is opt-in.
- Keep shared scripts and Alpine initialization outside replaced content.

## Forms and write responses

```blade
<form x-data="{ form: $form() }" x-form action="{{ route('posts.store') }}" method="post">
    @csrf
    <input name="title" type="text" required>
    <p x-show="form.hasError('title')" x-text="form.firstError('title')"></p>
    <p x-text="form.message"></p>
    <button type="submit" :disabled="form.processing">Publish</button>
</form>
```

For an existing Blade component, bind with `<x-button ::disabled="form.processing">Publish</x-button>` or `x-bind:disabled`: single-colon component bindings evaluate PHP.

Use a FormRequest for validation/authorization and a model/service for the write:

```php
// import the app's StorePostRequest, Post, and Spark\Http\Response
public function store(StorePostRequest $request): Response
{
    $post = Post::create($request->validated());

    return fire()->success('Post created.', ['id' => $post->id]);
}
```

**Client form behavior**

- `x-form` uses `form` from Alpine scope; `x-form="accountForm"` selects another property.
- `$form()` exposes `processing`, `message`, `errors`, `status`, `hasError()`, `firstError()`, `reset()`, `submit()`.
- Named controls, files, and the clicked submitter are serialized; arbitrary state properties are not request fields.
- A success envelope resets controls to defaults; validation/error results retain input. `form.reset()` only clears feedback state.

**Adapter response API**

| Intent | Call | Result |
| --- | --- | --- |
| Render page/partial | `fire($view, $props)` | `{html, title}` for FireLine; HTML for native visits |
| Success | `fire()->success($message, $data)` | `{status: 'success', message, data}` |
| Application error | `fire()->error($message, 400)` | `{status: 'error', message}` |
| Explicit validation errors | `fire()->handleValidation($message, ['title' => ['Required.']])` | HTTP 422 `{message, errors}` |
| Client navigation | `fire()->navigate((string) route('posts.index'))` | `{navigate: url}` |
| Full browser navigation | `fire()->redirect($url)` | `{redirect: url}` |

**Rules**

- `FireMiddleware` converts downstream Spark validation exceptions into the 422 envelope.
- Keep `@csrf`. For PATCH/PUT/DELETE use POST plus `@method(...)`, or the client's supported request method. FireLine sends the configured `csrfToken`; it does not create one.
- Navigation envelopes use HTTP 200 (their status argument applies to native redirects).
- Success/error/validation helpers **always return JSON**, even without JavaScript. If native form fallback is required, branch on `is_fire_js()` and return a normal post/redirect/get response for native requests.
- Never send raw HTML, 204/empty responses, or custom JSON shapes to a client expecting a FireLine envelope.

## Reactive components

Server-driven components: public PHP state + public action methods, synced over `POST /_fire/component`.

**Create and mount**

```sh
php spark make:fire Counter          # app/Components/Counter.php + resources/views/components/counter.blade.php
php spark make:fire Events/Discover  # app/Components/Events/Discover.php
```

```blade
@reactive('events.discover', ['query' => ''])   {{-- dot path => App\Components\Events\Discover --}}
@reactive(\App\Components\Counter::class, ['count' => 3])   {{-- class form also works --}}
```

PHP equivalents: `Fire::component(Counter::class, $props)` / `fire_component(...)` return an HTML string.

**Component class**

```php
namespace App\Components\Events;

use Spark\Fire\Component;

class Discover extends Component
{
    public string $query = '';
    public array $filters = ['category' => ''];   // initialize every public property
    public int $page = 1;

    protected function view(): string
    {
        return 'components.events.discover';
    }

    public function search(): void
    {
        $this->validate(['query' => 'max:100']);
        $this->page = 1;
    }
}
```

- State = initialized, public, non-static, non-readonly properties not starting with `_`. Keep it JSON-compatible: no models, services, files, or secrets.
- Template receives public state, `$component`, and `$errors` (`$errors->first('query')`). Escape values with `{{ }}`.
- Optional hooks: `boot()`, `mount(...$props)`, `hydrate()`, `dehydrate()`, `updatingX/updatedX`. `boot()` runs *before* state is restored; use `hydrate()` or the action for restored-state checks.
- Remote effects: `$this->dispatch('name', ...$params)`, `$this->navigate($url)`, `$this->redirect($url)`; map browser events with `protected array $listeners = ['event' => 'action'];`.

**View (no `x-data` on the wrapper, the adapter generates it)**

```blade
<div>
    <input fire:model.debounce.400ms="query" placeholder="Search">
    <select x-model="state.filters.category" @change="$fire.search()">...</select>
    <p>{{ $errors->first('query') }}</p>

    <button type="button" fire:click="search" fire:loading.attr="disabled" fire:target="search">Search</button>
    <span fire:loading fire:target="search">Loading…</span>

    @foreach ($events as $event)
        <article fire:key="event-{{ $event['id'] }}">{{ $event['title'] }}</article>
    @endforeach
</div>
```

| Binding | Use |
| --- | --- |
| `fire:model="prop"` | Two-way bind a **top-level** public property (250 ms debounce). `.debounce.500ms`, `.lazy`/`.change`, `.number`, `.trim` |
| `fire:click="action(args)"` | Call a public action; positional JS-expression args; `.prevent`, `.stop` |
| `fire:submit="action"` | Form submit action (always prevents native submit; never combine with `x-form`/`x-submit`) |
| `fire:loading` (`.remove`, `.attr="x"`, `.class="x"`, `.delay.300ms`) + `fire:target="action"` | Busy indicators |
| `fire:poll.5s="action"` | Poll; no expression = refresh |
| `fire:key="id"` | Stable literal key for list reconciliation |

JS inside a component: `$fire.prop`, `$fire.set('p', v)`, `await $fire.action(...)`, `$fire.call('action')`, `$fire.send()`. Nested data: `x-model="state.filters.query"` (PHP property must be pre-initialized with those keys). Avoid prop names like `call`, `get`, `set`, `send`. Inside a component `$fire` is the component; use `FireLine.context` for page navigation.

**Rules**

- Public state and action args are browser-editable: **validate and authorize inside every action**, not just `mount()`. Keep auth + CSRF middleware on `/_fire/component`.
- Validation returns HTTP 200 with the `errors` bag and re-rendered HTML (not 422). Failure in an update hook skips the action.
- Requests per component are serialized; local edits made mid-request sync afterward.
- Component requests skip page callbacks and global `fireStart`/`fireEnd`/progress bar; failures emit `fireError`.
- **No file uploads**: `fire:model` ignores `input[type=file]`. Use a `native` form or a separate upload endpoint, then store only an app-owned identifier in state (verify ownership).
- Nested components can remount when the parent re-renders; mount stateful ones as siblings.
- **419 errors**: `csrfToken` not set globally (see startup) or the `_token` meta tag is missing from the layout.

## Partials, preloads, polling, and state

| Feature | Behavior |
| --- | --- |
| `x-preload` / `x-preload.hover` | Preloads safe GET destinations. `is_fire_preload()` detects them. Return the same page fragment; omit only optional effects (e.g. page-view analytics). Never mutate state on a preloaded GET. |
| `x-poll="10000"` | Refreshes every 10 s. Pauses while hidden/offline or during foreground work. Pick an interval the UI justifies. |
| `$partial(url)` + `x-partial="state"` | Loads into the host's **children**, preserving the host. `state.load()` replaces/reconciles; `state.loadMore(nextUrl)` appends. |
| `x-partial.lazy` | Loads when visible. |

Partial rules:

- Detect with `is_fire_partial()`; return `Fire::render($fragmentView, $props)` with no layout.
- Partial HTML may have multiple roots or be empty, but still needs a JSON `html` envelope.
- Partials do not follow navigation envelopes or update history/title.
- Keep persistent controls outside the host. Avoid competing `x-html`/`x-text` on partial hosts.
- Use unique stable `key` attributes for reordered server-rendered children (Alpine `:key` belongs to `x-for`). Put third-party widgets behind an `x-ignore` boundary.

JS API: `$fire.navigate(url)`, `$fire.reload()`, `$fire.replaceHtml(html)` are asynchronous: await sequencing. `fireEnd` fires for failures/cancellations too and is not proof of success. Partial/preload requests do not emit global request events.

## Headers, deployment, and verification

- Requests send `X-FireLine: 1`, `X-Requested-With: XMLHttpRequest`, and normally `Accept: application/json`. Detection selects representation, **not authorization**.
- Preserve the adapter's `Vary` and cache-control headers so full documents and envelopes are not mixed by caches.
- **Asset versioning:** assign the same build identifier to the client `assetVersion` and server `Fire::version($version)` before rendering. The adapter does not derive a Vite version automatically. Encode client settings safely; never interpolate unescaped data into scripts.

**Verify**

1. Direct HTML load, client navigation, back/forward.
2. Successful write, invalid input (422 field arrays), CSRF rejection, auth failure.
3. Reactive component: `fire:model` sync and an action both succeed (no 419), validation errors render, loading indicators toggle.
4. For partials/preloads/versioning: cleanup, matching fragment roots, asset-mismatch behavior.
5. Rebuild changed frontend assets.

For exact behavior inspect the installed adapter (`src/FireService.php`, `FireMiddleware.php`, `FireHeaders.php`) and client package. Do not assume v1 APIs still exist.
