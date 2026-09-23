{{--
    A host layout reduced to what the contract needs: a title it reads from the
    screen's controller, and the slot the screen renders into. It stands in for
    a host shell in the tests that check a screen mounts inside one.

    Like every layout a screen names, it is a component: the screen opens its
    tag and fills its slot.
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>{{ $title ?? 'sans titre' }}</title>
</head>
<body>
    {{-- No apostrophe: `assertSee` escapes what it is given, and the template renders it raw. --}}
    <p>chrome fourni par le gabarit</p>
    {{ $slot }}
</body>
</html>
