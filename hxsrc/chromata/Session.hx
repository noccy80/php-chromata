package chromata;

import js.html.WebSocket;
import js.html.Event;
import js.html.MessageEvent;

/**
 * Session management in the browser/app container. Handles updates and events
 * via websockets.
 *
 */
class Session {

    /** @var The websocket connection */
    var ws:WebSocket;

    /** @var Connection status */
    var connected:Bool = false;

    var serializer:Serializer;

    /**
     * Constructor, takes the URL (without protocol) to the websocket server
     *
     */
    public function new(url:String) {
        trace("session:new (" + url + ")");

        serializer = new Serializer();

        trace("socket:connect");
        ws = new WebSocket("ws://" + url);
        ws.onopen = this.onSocketOpen;
        ws.onclose = this.onSocketClose;
        ws.onerror = this.onSocketError;
        ws.onmessage = this.onSocketMessage;
    }

    /**
     * Called when a session dom update is received
     *
     */
    private function onSessionDomUpdate(data:String) {
        js.Lib.eval(data);
        trace("session:domupdate");
    }

    /**
     * Called when a session status update is received
     *
     */
    private function onSessionStatus(data:String) {
        trace("session:status");
    }

    /**
     * Called when the socket is successfully opened
     *
     */
    private function onSocketOpen(e:Event) {
        trace("socket:open");
        connected = true;
    }

    /**
     * Called on socket close
     *
     */
    private function onSocketClose(e:Event) {
        trace("socket:close");
        connected = false;
    }

    /**
     * Called on socket error
     *
     */
    private function onSocketError(e:Event) {
        trace("socket:error");
    }

    /**
     * Called for each received frame
     *
     */
    private function onSocketMessage(e:MessageEvent) {
        trace("socket:message: " + e.data);
        
        var i = e.data.indexOf('|');
        var type = e.data.substring(0,i);
        var data = e.data.substring(i+1);

        trace(data);

        if (type == 'update') {
            this.onSessionDomUpdate(data);
        } else if (type == 'status') {
            this.onSessionStatus(data);
        }
    }

    /**
     * Send DOM event to the server
     *
     */
    public function sendDomEvent(e:Event)
    {
        var ser:String = this.serializer.serializeEvent(e);
        ws.send('event|' + ser);
    }

}
