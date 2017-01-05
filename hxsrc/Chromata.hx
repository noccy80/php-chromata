
import chromata.Session;
import js.html.Event;
import js.Browser;
import js.html.Element;

class Chromata {

    static var instance:Chromata;

    var session:Session;

    public function new(server:String) {
        session = new Session(server);        
    }

    public static function main() {
        var host:String = untyped __js__("CHROMATA_HOST");
        Chromata.instance = new Chromata(host);
    }

    public static function bindDomEvent(id:String, type:String) {
        var el:Element = js.Browser.document.getElementById(id);
        el.addEventListener(type, Chromata.dispatchDomEvent);
    }

    public static function dispatchDomEvent(e:Event) {
        Chromata.instance.session.sendDomEvent(e);
    }

}
