/**
 * @name        jQuery Fullscreen Plugin
 * @author      Klaus Herberth, Martin Angelov, Morten Sjøgren
 * @url         http://tutorialzine.com/2012/02/enhance-your-website-fullscreen-api/
 * @license     MIT License
 */

/*jshint browser: true, jquery: true */
(function($) {
    "use strict";

    // These helper functions available only to our plugin scope.
    function supportFullscreen() {
        var doc = document.documentElement;

        return ('requestFullscreen' in doc) ||
                ('mozRequestFullscreen' in doc && document.mozFullscreenEnabled) ||
                ('webkitRequestFullscreen' in doc);
    }

    function requestFullscreen(elem) {
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.mozRequestFullscreen) {
            elem.mozRequestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
        }
    }

    function fullscreenStatus() {
        return document.fullscreen ||
                document.mozFullScreen ||
                document.webkitIsFullScreen ||
                false;
    }

    function cancelFullscreen() {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.mozCancelFullscreen) {
            document.mozCancelFullScreen();
        } else if (document.webkitCancelFullScreen) {
            document.webkitCancelFullScreen();
        }
    }

    // Adding a new test to the jQuery support object
    $.support.fullscreen = supportFullscreen();

    // Creating the plugin
    $.fn.fullscreen = function() {
        if (!$.support.fullscreen || this.length !== 1)
            return this;

        if (fullscreenStatus()) {
            // if we are already in fullscreen, exit
            cancelFullscreen();
            return this;
        }
        
        var self = this;

        $(self).on('fullscreenerror mozfullscreenerror webkitfullscreenerror msfullscreenerror', function() {
            $(document).trigger('error.fullscreen');
        });

        $(self).on('fullscreenchange mozfullscreenchange webkitfullscreenchange msfullscreenchange', function() {
            if (fullscreenStatus()){
                $(document).trigger('enabled.fullscreen');
            }else{
                $(document).trigger('disabled.fullscreen');
                $(self).off('fullscreenchange mozfullscreenchange webkitfullscreenchange msfullscreenchange');
            }
        });

        requestFullscreen($(self).get(0));

        return $(self);
    };

    $.fn.cancelFullscreen = function( ) {
        cancelFullscreen();

        return this;
    };
}(jQuery));