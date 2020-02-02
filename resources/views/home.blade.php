@extends('layouts.app')

@push('pusherjs')
    <script src="https://js.pusher.com/5.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style type="text/css">
        .meveto-info-toaster {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            font-size: 0.8rem;
            max-width: max-content!important;
        }
    </style>
    <script>
        // Enable pusher logging - don't include this in production
        Pusher.logToConsole = true;

        var pusher = new Pusher('259c40c31c2089b1b00c', {
            cluster: 'mt1',
            forceTLS: true,
            authEndpoint: 'broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-Token': "{{ csrf_token() }}"
                }
            }
        });

        var channel = pusher.subscribe("{{ $logout_channel }}");
        var home = "{{ url('/') }}"
        channel.bind('user-logged-out', function(data) {
            Toastify({
                text: data.name+"! You have been logged out from this website using your Meveto dashboard.",
                duration: 3000,
                newWindow: true,
                close: false,
                gravity: "top",
                position: 'center',
                backgroundColor: "linear-gradient(to right, #0079bb, #3CC98E)",
                stopOnFocus: true,
                className: 'meveto-info-toaster',
                onClick: function(){}
            }).showToast();
            setTimeout(function(){
                window.location.replace(home);
            }, 3000)
        });

    </script>
@endpush

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Dashboard</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @auth
                        <div class="title">
                            Welcome to Meveto PHP Demo {{ Auth::user()->name }}
                        </div>
                        You are logged in!
                    @endauth
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
