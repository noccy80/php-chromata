<?php

use NoccyLabs\Chromata\App\App;
use NoccyLabs\Chromata\App\DomFactory as DOM;

class MyApp extends App
{
    protected $sentences = [
        "Hello World!",
        "I am running server-side yo!",
        "That means this is an actual application",
        "That is running in your browser",
        "Pretty awesome huh?"
    ];

    protected function create()
    {
        $root = DOM::div();

        $text1 = DOM::span()
                ->setAttribute('style', 'color:blue;');
        $root->appendChild($text1);
        /*
        $time = DOM::span()
                ->setAttribute('style', 'color:red;');
        $root->appendChild($time);

        $text2 = DOM::div()
                ->setAttribute('style','font:14pt sans-serif; color:#446688;');
        $root->appendChild($text2);
        */

        $pad = 0;
        $button = DOM::button()
                ->setValue("Click me")
                ->setAttribute('style',"margin:10px;")
                ->addEventListener('click', function ($e) use (&$pad) {
                    $e->target->setValue("Awesome!");
                    $e->target->setAttribute('style',"margin:10px; padding: ".++$pad."px;");
                });

        $root->appendChild($button);

        $this->setRoot($root);

        // $this->timeElem = $time;
        $this->textElem = $text1;
    }

    protected function update()
    {
        static $n;
        /*
        $this->timeElem->setValue(date(DATE_RFC822));
        */
        $this->textElem->setValue(join(" ",sys_getloadavg()));

        // we want to get called again in ~1.0 seconds. returning false will
        // abort the application and exit.
        return 0.1;
    }

    protected function destroy()
    {

    }
}

return MyApp::class;
