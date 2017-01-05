Chromata (Experiment)
=====================

Chromata is an experiment to determine if it is possible to create a
realtime link between a web browser/webapp container and an application
instance running on the server. Basically:

1.  Upon receiving an incoming websocket connection, a new session is
    started together with an application instance.
2.  The application builds its initial DOM, hooks events etc, and all
    updates are sent to the client over the websocket.
3.  The client displays the received DOM, attaches events etc.
4.  Events are now sent to the application instance in real-time, while
    at the same time updated parts of the DOM is sent to the client.

## Components and Debugging

There is a demo app living in the `app` directory. To test the apphost
part of things, invoke `chromata-app --appdir app`. You should see the
update frames appearing in the console, together with log information.
To exit, press Ctrl-C.

The websocket proxy relays messages between the browser and an app
instance. You can try the websocket end of things with the `wscat`
utility (`npm install -g wscat`).

## Launching the demo app

To launch the demo app, all you have to do is call on `bin/chromata`:

    $ bin/chromata

You can specify the base port using `--port` and also auto-spawn a
UI using `--ui <type>`:

    $ bin/chromata --ui chrome

The daemon will now spawn, loading the websocket proxy, httpd and related
components. If you used `--ui chrome` a chrome web application window will
also open, and you will start to see output in the terminal and browser
as the websocket connection is established.

## Rebuilding the javascript code

Chromata makes use of *haxe* to build the javascript for the UI. To
rebuild it:

    $ haxe build.hxml

A new `web/js/chromata.js` should now have been created.

## Known issues

 *  The `webapp-container` provided by Ubuntu doesn't seem to support
    websockets, or at least the websockets are not connecting back to
    the server. So you want to use `chrome` or `google-chrome` for things
    to work properly.

## Todos

 *  Move the application management out of the main Chromata Application
    class. It would do better in the `..Chromata\App` namespace.

## Credits and Acknowledgements

 *  Rolf Rottman's blog post "A command line WebSocket Client" for pointing
    me to `wscat` -
    https://blog.grandcentrix.net/a-command-line-websocket-client/
