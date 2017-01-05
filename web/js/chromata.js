(function (console, $global) { "use strict";
function $extend(from, fields) {
	function Inherit() {} Inherit.prototype = from; var proto = new Inherit();
	for (var name in fields) proto[name] = fields[name];
	if( fields.toString !== Object.prototype.toString ) proto.toString = fields.toString;
	return proto;
}
var Chromata = function(server) {
	this.session = new chromata.Session(server);
};
Chromata.__name__ = true;
Chromata.main = function() {
	var host = CHROMATA_HOST;
	Chromata.instance = new Chromata(host);
};
Chromata.bindDomEvent = function(id,type) {
	var el = window.document.getElementById(id);
	el.addEventListener(type,Chromata.dispatchDomEvent);
};
Chromata.dispatchDomEvent = function(e) {
	Chromata.instance.session.sendDomEvent(e);
};
Chromata.prototype = {
	__class__: Chromata
};
Math.__name__ = true;
var Std = function() { };
Std.__name__ = true;
Std.string = function(s) {
	return js.Boot.__string_rec(s,"");
};
var chromata = {};
chromata.Serializer = function() {
};
chromata.Serializer.__name__ = true;
chromata.Serializer.prototype = {
	serializeEvent: function(e) {
		var ret = { type : e.type, target : { id : e.target.id}, data : null};
		var data = null;
		if(e.type == "click" || e.type.indexOf("mouse") == 0) {
			var me;
			me = js.Boot.__cast(e , MouseEvent);
			data = { x : me.clientX, y : me.clientY};
		} else if(e.type.indexOf("key") == 0) {
			var ke;
			ke = js.Boot.__cast(e , KeyboardEvent);
			data = { key : ke.key, keyCode : ke.keyCode, modifiers : { alt : ke.altKey, shift : ke.shiftKey, ctrl : ke.ctrlKey, meta : ke.metaKey}, repeat : ke.repeat};
		} else console.log(e);
		ret.data = data;
		var str = JSON.stringify(ret);
		console.log(str);
		return str;
	}
	,__class__: chromata.Serializer
};
chromata.Session = function(url) {
	this.connected = false;
	console.log("session:new (" + url + ")");
	this.serializer = new chromata.Serializer();
	console.log("socket:connect");
	this.ws = new WebSocket("ws://" + url);
	this.ws.onopen = $bind(this,this.onSocketOpen);
	this.ws.onclose = $bind(this,this.onSocketClose);
	this.ws.onerror = $bind(this,this.onSocketError);
	this.ws.onmessage = $bind(this,this.onSocketMessage);
};
chromata.Session.__name__ = true;
chromata.Session.prototype = {
	onSessionDomUpdate: function(data) {
		eval(data);
		console.log("session:domupdate");
	}
	,onSessionStatus: function(data) {
		console.log("session:status");
	}
	,onSocketOpen: function(e) {
		console.log("socket:open");
		this.connected = true;
	}
	,onSocketClose: function(e) {
		console.log("socket:close");
		this.connected = false;
	}
	,onSocketError: function(e) {
		console.log("socket:error");
	}
	,onSocketMessage: function(e) {
		console.log("socket:message: " + Std.string(e.data));
		var i = e.data.indexOf("|");
		var type = e.data.substring(0,i);
		var data = e.data.substring(i + 1);
		console.log(data);
		if(type == "update") this.onSessionDomUpdate(data); else if(type == "status") this.onSessionStatus(data);
	}
	,sendDomEvent: function(e) {
		var ser = this.serializer.serializeEvent(e);
		this.ws.send("event|" + ser);
	}
	,__class__: chromata.Session
};
var js = {};
js._Boot = {};
js._Boot.HaxeError = function(val) {
	Error.call(this);
	this.val = val;
	this.message = String(val);
	if(Error.captureStackTrace) Error.captureStackTrace(this,js._Boot.HaxeError);
};
js._Boot.HaxeError.__name__ = true;
js._Boot.HaxeError.__super__ = Error;
js._Boot.HaxeError.prototype = $extend(Error.prototype,{
	__class__: js._Boot.HaxeError
});
js.Boot = function() { };
js.Boot.__name__ = true;
js.Boot.getClass = function(o) {
	if((o instanceof Array) && o.__enum__ == null) return Array; else {
		var cl = o.__class__;
		if(cl != null) return cl;
		var name = js.Boot.__nativeClassName(o);
		if(name != null) return js.Boot.__resolveNativeClass(name);
		return null;
	}
};
js.Boot.__string_rec = function(o,s) {
	if(o == null) return "null";
	if(s.length >= 5) return "<...>";
	var t = typeof(o);
	if(t == "function" && (o.__name__ || o.__ename__)) t = "object";
	switch(t) {
	case "object":
		if(o instanceof Array) {
			if(o.__enum__) {
				if(o.length == 2) return o[0];
				var str2 = o[0] + "(";
				s += "\t";
				var _g1 = 2;
				var _g = o.length;
				while(_g1 < _g) {
					var i1 = _g1++;
					if(i1 != 2) str2 += "," + js.Boot.__string_rec(o[i1],s); else str2 += js.Boot.__string_rec(o[i1],s);
				}
				return str2 + ")";
			}
			var l = o.length;
			var i;
			var str1 = "[";
			s += "\t";
			var _g2 = 0;
			while(_g2 < l) {
				var i2 = _g2++;
				str1 += (i2 > 0?",":"") + js.Boot.__string_rec(o[i2],s);
			}
			str1 += "]";
			return str1;
		}
		var tostr;
		try {
			tostr = o.toString;
		} catch( e ) {
			if (e instanceof js._Boot.HaxeError) e = e.val;
			return "???";
		}
		if(tostr != null && tostr != Object.toString && typeof(tostr) == "function") {
			var s2 = o.toString();
			if(s2 != "[object Object]") return s2;
		}
		var k = null;
		var str = "{\n";
		s += "\t";
		var hasp = o.hasOwnProperty != null;
		for( var k in o ) {
		if(hasp && !o.hasOwnProperty(k)) {
			continue;
		}
		if(k == "prototype" || k == "__class__" || k == "__super__" || k == "__interfaces__" || k == "__properties__") {
			continue;
		}
		if(str.length != 2) str += ", \n";
		str += s + k + " : " + js.Boot.__string_rec(o[k],s);
		}
		s = s.substring(1);
		str += "\n" + s + "}";
		return str;
	case "function":
		return "<function>";
	case "string":
		return o;
	default:
		return String(o);
	}
};
js.Boot.__interfLoop = function(cc,cl) {
	if(cc == null) return false;
	if(cc == cl) return true;
	var intf = cc.__interfaces__;
	if(intf != null) {
		var _g1 = 0;
		var _g = intf.length;
		while(_g1 < _g) {
			var i = _g1++;
			var i1 = intf[i];
			if(i1 == cl || js.Boot.__interfLoop(i1,cl)) return true;
		}
	}
	return js.Boot.__interfLoop(cc.__super__,cl);
};
js.Boot.__instanceof = function(o,cl) {
	if(cl == null) return false;
	switch(cl) {
	case Int:
		return (o|0) === o;
	case Float:
		return typeof(o) == "number";
	case Bool:
		return typeof(o) == "boolean";
	case String:
		return typeof(o) == "string";
	case Array:
		return (o instanceof Array) && o.__enum__ == null;
	case Dynamic:
		return true;
	default:
		if(o != null) {
			if(typeof(cl) == "function") {
				if(o instanceof cl) return true;
				if(js.Boot.__interfLoop(js.Boot.getClass(o),cl)) return true;
			} else if(typeof(cl) == "object" && js.Boot.__isNativeObj(cl)) {
				if(o instanceof cl) return true;
			}
		} else return false;
		if(cl == Class && o.__name__ != null) return true;
		if(cl == Enum && o.__ename__ != null) return true;
		return o.__enum__ == cl;
	}
};
js.Boot.__cast = function(o,t) {
	if(js.Boot.__instanceof(o,t)) return o; else throw new js._Boot.HaxeError("Cannot cast " + Std.string(o) + " to " + Std.string(t));
};
js.Boot.__nativeClassName = function(o) {
	var name = js.Boot.__toStr.call(o).slice(8,-1);
	if(name == "Object" || name == "Function" || name == "Math" || name == "JSON") return null;
	return name;
};
js.Boot.__isNativeObj = function(o) {
	return js.Boot.__nativeClassName(o) != null;
};
js.Boot.__resolveNativeClass = function(name) {
	return $global[name];
};
var $_, $fid = 0;
function $bind(o,m) { if( m == null ) return null; if( m.__id__ == null ) m.__id__ = $fid++; var f; if( o.hx__closures__ == null ) o.hx__closures__ = {}; else f = o.hx__closures__[m.__id__]; if( f == null ) { f = function(){ return f.method.apply(f.scope, arguments); }; f.scope = o; f.method = m; o.hx__closures__[m.__id__] = f; } return f; }
String.prototype.__class__ = String;
String.__name__ = true;
Array.__name__ = true;
var Int = { __name__ : ["Int"]};
var Dynamic = { __name__ : ["Dynamic"]};
var Float = Number;
Float.__name__ = ["Float"];
var Bool = Boolean;
Bool.__ename__ = ["Bool"];
var Class = { __name__ : ["Class"]};
var Enum = { };
js.Boot.__toStr = {}.toString;
Chromata.main();
})(typeof console != "undefined" ? console : {log:function(){}}, typeof window != "undefined" ? window : typeof global != "undefined" ? global : typeof self != "undefined" ? self : this);
