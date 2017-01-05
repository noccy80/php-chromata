package chromata;

import js.Lib;
import js.html.WebSocket;
import js.html.Event;
import js.html.Document;
import js.html.Element;
import js.html.HTMLCollection;

/**
 * Serialize events
 *
 */
class Serializer {

    /**
     * Constructor
     *
     */
    public function new() {
    }

    public function serializeEvent(e:Event):String {

        var ret = {
            type: e.type,
            target: {
                id: untyped __js__("e.target.id")
            },
            data: null
        };

        var data:Dynamic = null;
        if ((e.type == 'click') || (e.type.indexOf('mouse')==0))  {
            var me:js.html.MouseEvent = cast(e, js.html.MouseEvent);
            data = {
                x: me.clientX,
                y: me.clientY
            };
        } else if (e.type.indexOf('key')==0) {
            var ke:js.html.KeyboardEvent = cast(e, js.html.KeyboardEvent);
            data = {
                key: ke.key,
                keyCode: ke.keyCode,
                modifiers: {
                    alt: ke.altKey,
                    shift: ke.shiftKey,
                    ctrl: ke.ctrlKey,
                    meta: ke.metaKey
                },
                repeat: ke.repeat
            };

        } else {
            trace(e);
        }
        ret.data = data;
        
        var str:String = haxe.Json.stringify(ret);
        trace(str);
        return str;
    }

}
