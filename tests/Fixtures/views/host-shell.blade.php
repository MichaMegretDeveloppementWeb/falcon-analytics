{{--
    A host layout, reduced to what the contract needs: a title it reads from the
    screen's controller, and the section the screen renders into.

    Stands in for the real thing (a sidebar, a topbar, a theme toggle) in the
    tests that check a screen is mounted inside a host shell rather than the
    package's own.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>{{ $analyticsTitle ?? 'sans titre' }}</title>
</head>
<body>
    {{-- Sans apostrophe, délibérément · `assertSee` échappe ce qu'on lui donne,
         et un gabarit rend son HTML brut. Les deux ne se rencontreraient pas. --}}
    <p>chrome fourni par le gabarit</p>
    @yield('content')
</body>
</html>
