{{--
    A host layout, reduced to what the contract needs: a title it reads from the
    screen's controller, and the slot the screen renders into.

    Stands in for the real thing (a sidebar, a topbar, a theme toggle) in the
    tests that check a screen is mounted inside a host shell rather than the
    package's own.

    It is a component, like every layout a screen names: a screen opens a tag
    and fills its slot. A host whose own shell is written as a component — the
    ordinary case — names it here and writes no bridge view.
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>{{ $title ?? 'sans titre' }}</title>
</head>
<body>
    {{-- No apostrophe, deliberately: `assertSee` escapes what it is given, and
         a template renders its HTML raw. The two would never meet. --}}
    <p>chrome fourni par le gabarit</p>
    {{ $slot }}
</body>
</html>
