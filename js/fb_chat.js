(function($) {

    var defaults = {
        linkClass: 'fbcLaunch',
        requestPath: 'N/A',
        heartbeatMin: 5000,
        heartbeatMax: 30000
    }

    $.fn.fb_chat = function(options) {
        // Create namespace
        var fb_chat = {};

        var init = function() {
            // Merge options
            fb_chat.settings = $.extend({}, defaults, options);

            fb_chat.settings.heartbeat = fb_chat.settings.heartbeatMin;
            fb_chat.settings.heartbeatCount = 0;

            fb_chat.settings.chatBoxes = new Array();
            fb_chat.settings.chatboxFocus = new Array();
            fb_chat.settings.newMessages = new Array();
            fb_chat.settings.newMessagesWin = new Array();

            fb_chat.settings.windowFocus = true;
            fb_chat.settings.originalTitle = "";
            fb_chat.settings.blinkOrder = 0;

            setup();
        };

        var setup = function() {
            _setup_build_structure();
            _setup_init_events();

            // Store original document title
            fb_chat.settings.originalTitle = document.title;

            chat_start_session();

            $([window, document]).blur(function() {
                fb_chat.settings.windowFocus = false;
            }).focus(function() {
                fb_chat.settings.windowFocus = true;
                document.title = fb_chat.settings.originalTitle;
            });
        };

        var _setup_build_structure = function() {
            $('body').wrapInner(function() {
                var wrapper = '<div id="FBChatMain"></div>';
                return wrapper;
            });
        };

        var _setup_init_events = function() {
            var launchClass = fb_chat.settings.linkClass;
            $('.' + launchClass).click(function() {
                chat_start_conversation(this);
            });
        };

        var chat_start_conversation = function(obj) {
            tid = $(obj).attr("fb-data");
            chat_create_chatbox(tid);
            $("#chatbox_" + tid + " .chatboxtextarea").focus();
        };

        var chat_create_chatbox = function(tid, minimizeChatBox) {
            if ($("#chatbox_" + tid).length > 0) {
                if ($("#chatbox_" + tid).css('display') == 'none') {
                    $("#chatbox_" + tid).css('display', 'block');
                    chat_restructure_boxes();
                }
                $("#chatbox_" + tid + " .chatboxtextarea").focus();
                return;
            }

            cbHTML = '<div class="chatboxhead">';
            cbHTML += '<div class="chatboxtitle">N/A</div>';
            cbHTML += '<div class="chatboxoptions">';
            cbHTML += '<a id="close" href="javascript:void(0)">X</a>';
            cbHTML += '</div>';
            cbHTML += '<br clear="all"/>';
            cbHTML += '</div>';
            cbHTML += '<div class="chatboxcontent"></div>';
            cbHTML += '<div class="chatboxinput">';
            cbHTML += '<textarea class="chatboxtextarea" maxlength="255"></textarea>';
            cbHTML += '</div>';

            $("<div />").attr("id", "chatbox_" + tid).addClass("chatbox").html(cbHTML).appendTo($("body")).ready(function() {
                get_user_name(tid);
            });

            $("#chatbox_" + tid).css('bottom', '0px');

            chatBoxeslength = 0;
            for (x in fb_chat.settings.chatBoxes) {
                if ($("#chatbox_" + fb_chat.settings.chatBoxes[x]).css('display') != 'none') {
                    chatBoxeslength++;
                }
            }

            if (chatBoxeslength == 0) {
                $("#chatbox_" + tid).css('right', '20px');
            } else {
                width = chatBoxeslength * (250 + 7) + 20;
                $("#chatbox_" + tid).css('right', width + 'px');
            }

            fb_chat.settings.chatBoxes.push(tid);

            if (minimizeChatBox == 1) {
                minimizedChatBoxes = new Array();
                if (cookie('chatbox_minimized')) {
                    minimizedChatBoxes = cookie('chatbox_minimized').split(/\|/);
                }

                minimize = 0;
                for (j = 0; j < minimizedChatBoxes.length; j++) {
                    if (minimizedChatBoxes[j] == tid) {
                        minimize = 1;
                    }
                }

                if (minimize == 1) {
                    $('#chatbox_' + tid + ' .chatboxcontent').css('display', 'none');
                    $('#chatbox_' + tid + ' .chatboxinput').css('display', 'none');
                }
            }

            fb_chat.settings.chatboxFocus[tid] = false;

            $("#chatbox_" + tid + " .chatboxtextarea").blur(function() {
                fb_chat.settings.chatboxFocus[tid] = false;
                $("#chatbox_" + tid + " .chatboxtextarea").removeClass('chatboxtextareaselected');
            }).focus(function() {
                fb_chat.settings.chatboxFocus[tid] = true;
                fb_chat.settings.newMessages[tid] = false;
                $('#chatbox_' + tid + ' .chatboxhead').removeClass('chatboxblink');
                $("#chatbox_" + tid + " .chatboxtextarea").addClass('chatboxtextareaselected');
            });

            $("#chatbox_" + tid).click(function() {
                if ($('#chatbox_' + tid + ' .chatboxcontent').css('display') != 'none') {
                    $("#chatbox_" + tid + " .chatboxtextarea").focus();
                }
            });

            $("#chatbox_" + tid + " .chatboxtitle").click(function() {
                chat_toggle_chatbox(tid);
            });

            $("#chatbox_" + tid + " #close").click(function() {
                chat_close_chatbox(tid);
            });

            $("#chatbox_" + tid + " .chatboxtextarea").keydown(function(event) {
                check_input_key(event, this, tid);
                if (event.keyCode == 13 && event.shiftKey == 0) {
                    return false;
                }
            });

            $("#chatbox_" + tid).show();
        };

        var chat_toggle_chatbox = function(tid) {
            if ($('#chatbox_' + tid + ' .chatboxcontent').css('display') == 'none') {
                var minimizedChatBoxes = new Array();
                if (cookie('chatbox_minimized')) {
                    minimizedChatBoxes = cookie('chatbox_minimized').split(/\|/);
                }

                newCookie = '';
                for (i = 0; i < minimizedChatBoxes.length; i++) {
                    if (minimizedChatBoxes[i] != tid) {
                        newCookie += tid + '|';
                    }
                }

                newCookie = newCookie.slice(0, -1);
                cookie('chatbox_minimized', newCookie);

                $('#chatbox_' + tid + ' .chatboxcontent').css('display', 'block');
                $('#chatbox_' + tid + ' .chatboxinput').css('display', 'block');

                scHeight = $("#chatbox_" + tid + " .chatboxcontent")[0].scrollHeight;
                $("#chatbox_" + tid + " .chatboxcontent").scrollTop(scHeight);
            } else {
                newCookie = tid;
                if (cookie('chatbox_minimized')) {
                    newCookie += '|' + cookie('chatbox_minimized');
                }

                cookie('chatbox_minimized', newCookie);

                $('#chatbox_' + tid + ' .chatboxcontent').css('display', 'none');
                $('#chatbox_' + tid + ' .chatboxinput').css('display', 'none');
            }
        };

        var chat_close_chatbox = function(tid) {
            $('#chatbox_' + tid).css('display', 'none');
            chat_restructure_boxes();
            $.post(fb_chat.settings.requestPath + "/fb_chat.php?a=3", {
                chatbox: tid
            });
        };

        var chat_restructure_boxes = function() {
            align = 0;
            for (x in fb_chat.settings.chatBoxes) {
                tid = fb_chat.settings.chatBoxes[x];
                if ($("#chatbox_" + tid).css('display') != 'none') {
                    if (align == 0) {
                        $("#chatbox_" + tid).css('right', '20px');
                    } else {
                        width = (align) * (250 + 7) + 20;
                        $("#chatbox_" + tid).css('right', width + 'px');
                    }
                    align++;
                }
            }
        };

        var chat_heartbeat = function() {
            var itemsfound = 0;

            if (fb_chat.settings.windowFocus == false) {
                var blinkNumber = 0;
                var titleChanged = 0;
                for (x in fb_chat.settings.newMessagesWin) {
                    if (fb_chat.settings.newMessagesWin[x] == true) {
                        ++blinkNumber;
                        if (blinkNumber >= fb_chat.settings.blinkOrder) {
                            name = $("#chatbox_" + x + " .chatboxtitle").text();
                            document.title = name + '...';
                            titleChanged = 1;
                            break;
                        }
                    }
                }
                if (titleChanged == 0) {
                    document.title = fb_chat.settings.originalTitle;
                    fb_chat.settings.blinkOrder = 0;
                } else {
                    ++fb_chat.settings.blinkOrder;
                }
            } else {
                for (x in fb_chat.settings.newMessagesWin) {
                    fb_chat.settings.newMessagesWin[x] = false;
                }
            }

            for (x in fb_chat.settings.newMessages) {
                if (fb_chat.settings.newMessages[x] == true) {
                    if (fb_chat.settings.chatboxFocus[x] == false) {
                        $('#chatbox_' + x + ' .chatboxhead').toggleClass('chatboxblink');
                    }
                }
            }

            $.ajax({
                url: fb_chat.settings.requestPath + "/fb_chat.php?a=2",
                cache: false,
                dataType: "json",
                success: function(data) {
                    $.each(data.items, function(i, item) {
                        if (item) {
                            tid = item.f.id;

                            if ($("#chatbox_" + tid).length <= 0) {
                                chat_create_chatbox(tid);
                            }

                            if ($("#chatbox_" + tid).css('display') == 'none') {
                                $("#chatbox_" + tid).css('display', 'block');
                                chat_restructure_boxes();
                            }

                            if (item.s == 2) {
                                appHTML = '<div class="chatboxmessage">';
                                appHTML += '<span class="chatboxinfo">' + item.m + '</span>';
                                appHTML += '</div>';
                                $("#chatbox_" + tid + " .chatboxcontent").append(appHTML);
                            } else {
                                fb_chat.settings.newMessages[tid] = true;
                                fb_chat.settings.newMessagesWin[tid] = true;
                                appHTML = '<div class="chatboxmessage">';
                                appHTML += '<span class="chatboxmessagefrom"></span>';
                                appHTML += '<br />';
                                appHTML += '<span class="chatboxmessagecontent"></span>';
                                appHTML += '</div>';
                                $("#chatbox_" + tid + " .chatboxcontent").append(appHTML);
                                $("#chatbox_" + tid + " .chatboxmessagefrom").last().html(item.f.name + ":&nbsp;&nbsp;").text();
                                $("#chatbox_" + tid + " .chatboxmessagecontent").last().html(item.m);
                            }

                            scHeight = $("#chatbox_" + tid + " .chatboxcontent")[0].scrollHeight;
                            $("#chatbox_" + tid + " .chatboxcontent").scrollTop(scHeight);
                            itemsfound += 1;
                        }
                    });

                    fb_chat.settings.heartbeatCount++;

                    if (itemsfound > 0) {
                        fb_chat.settings.heartbeat = fb_chat.settings.heartbeatMin;
                        fb_chat.settings.heartbeatCount = 1;
                    } else if (fb_chat.settings.heartbeatCount >= 10) {
                        fb_chat.settings.heartbeat *= 2;
                        fb_chat.settings.heartbeatCount = 1;

                        if (fb_chat.settings.heartbeat > fb_chat.settings.heartbeatMax) {
                            fb_chat.settings.heartbeat = fb_chat.settings.heartbeatMax;
                        }
                    }

                    setTimeout(function() {
                        chat_heartbeat();
                    }, fb_chat.settings.heartbeat);
                }
            });
        };

        var chat_start_session = function() {
            $.ajax({
                url: fb_chat.settings.requestPath + "/fb_chat.php?a=1",
                cache: false,
                dataType: "json",
                success: function(data) {
                    $.each(data.items, function(i, item) {
                        if (item) {
                            if ($("#chatbox_" + item.f.id).length <= 0) {
                                chat_create_chatbox(item.f.id, 1);
                            }
                            if (item.s == 2) {
                                appHTML = '<div class="chatboxmessage">';
                                appHTML += '<span class="chatboxinfo"></span>';
                                appHTML += '</div>';
                                $("#chatbox_" + item.f.id + " .chatboxcontent").append(appHTML);
                                $("#chatbox_" + item.f.id + " .chatboxinfo").last().html(item.m);
                            } else {
                                appHTML = '<div class="chatboxmessage">';
                                appHTML += '<span class="chatboxmessagefrom"></span>';
                                appHTML += '<br />';
                                appHTML += '<span class="chatboxmessagecontent"></span>';
                                appHTML += '</div>';
                                $("#chatbox_" + item.f.id + " .chatboxcontent").append(appHTML);
                                $("#chatbox_" + item.f.id + " .chatboxmessagefrom").last().html(item.f.name + ":&nbsp;&nbsp;").text();
                                $("#chatbox_" + item.f.id + " .chatboxmessagecontent").last().html(item.m);
                            }
                        }
                    });

                    for (i = 0; i < fb_chat.settings.chatBoxes.length; i++) {
                        tid = fb_chat.settings.chatBoxes[i];
                        scHeight = $("#chatbox_" + tid + " .chatboxcontent")[0].scrollHeight;
                        $("#chatbox_" + tid + " .chatboxcontent").scrollTop(scHeight);
                        setTimeout('$("#chatbox_" + tid + " .chatboxcontent").scrollTop($("#chatbox_" + tid + " .chatboxcontent")[0].scrollHeight);', 100);
                    }

                    setTimeout(function() {
                        chat_heartbeat();
                    }, fb_chat.settings.heartbeat);
                }
            });
        };

        var check_input_key = function(event, chatboxtextarea, tid) {
            if (event.keyCode == 13 && event.shiftKey == 0) {
                message = $(chatboxtextarea).val();
                message = message.replace(/^\s+|\s+$/g, "");
                if (message != "") {
                    $.ajax({
                        type: "POST",
                        url: fb_chat.settings.requestPath + "/fb_chat.php?a=4",
                        data: {
                            to: tid, 
                            message: message
                        },
                        cache: false,
                        dataType: "json",
                        success: function(data) {
                            cbHTML = '<div class="chatboxmessage">';
                            cbHTML += '<span class="chatboxmessagefrom"></span>';
                            cbHTML += '<br />';
                            cbHTML += '<span class="chatboxmessagecontent"></span>';
                            cbHTML += '</div>';
                            $("#chatbox_" + tid + " .chatboxcontent").append(cbHTML);
                            $("#chatbox_" + tid + " .chatboxmessagefrom").last().html(data.f + ":&nbsp;&nbsp;").text();
                            $("#chatbox_" + tid + " .chatboxmessagecontent").last().html(data.m);
                            scHeight = $("#chatbox_" + tid + " .chatboxcontent")[0].scrollHeight;
                            $("#chatbox_" + tid + " .chatboxcontent").scrollTop(scHeight);
                        }
                    });
                }

                $(chatboxtextarea).val('');
                $(chatboxtextarea).focus();
                $(chatboxtextarea).css('height', '20px');

                fb_chat.settings.heartbeat = fb_chat.settings.heartbeatMin;
                fb_chat.settings.heartbeatCount = 1;
            } else {
                var adjustedHeight = chatboxtextarea.clientHeight;
                var maxHeight = 94;
                if (maxHeight > adjustedHeight) {
                    adjustedHeight = Math.max(chatboxtextarea.scrollHeight, adjustedHeight);
                    if (maxHeight) {
                        adjustedHeight = Math.min(maxHeight, adjustedHeight);
                    }
                    if (adjustedHeight > chatboxtextarea.clientHeight) {
                        $(chatboxtextarea).css('height', adjustedHeight + 8 + 'px');
                    }
                } else {
                    $(chatboxtextarea).css('overflow', 'auto');
                }
            }
        };

        var get_user_name = function(tid) {
            $.post(fb_chat.settings.requestPath + "/fb_chat.php?a=5", {
                tid: tid
            }, function(data) {
                $("#chatbox_" + tid + " .chatboxtitle").html(data.name);
            });
        };

        /**
         * Cookie plugin
         *
         * Copyright (c) 2006 Klaus Hartl (stilbuero.de)
         * Dual licensed under the MIT and GPL licenses:
         * http://www.opensource.org/licenses/mit-license.php
         * http://www.gnu.org/licenses/gpl.html
         *
         */
        var cookie = cookie = function(name, value, options) {
            if (typeof value != 'undefined') { // name and value given, set cookie
                options = options || {};
                if (value === null) {
                    value = '';
                    options.expires = -1;
                }
                var expires = '';
                if (options.expires && (typeof options.expires == 'number' || options.expires.toUTCString)) {
                    var date;
                    if (typeof options.expires == 'number') {
                        date = new Date();
                        date.setTime(date.getTime() + (options.expires * 24 * 60 * 60 * 1000));
                    } else {
                        date = options.expires;
                    }
                    expires = '; expires=' + date.toUTCString(); // use expires attribute, max-age is not supported by IE
                }
                // CAUTION: Needed to parenthesize options.path and options.domain
                // in the following expressions, otherwise they evaluate to undefined
                // in the packed version for some reason...
                var path = options.path ? '; path=' + (options.path) : '';
                var domain = options.domain ? '; domain=' + (options.domain) : '';
                var secure = options.secure ? '; secure' : '';
                document.cookie = [name, '=', encodeURIComponent(value), expires, path, domain, secure].join('');
            } else { // only name given, get cookie
                var cookieValue = null;
                if (document.cookie && document.cookie != '') {
                    var cookies = document.cookie.split(';');
                    for (var i = 0; i < cookies.length; i++) {
                        var cookie = jQuery.trim(cookies[i]);
                        // Does this cookie string begin with the name we want?
                        if (cookie.substring(0, name.length + 1) == (name + '=')) {
                            cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
                            break;
                        }
                    }
                }
                return cookieValue;
            }
        };

        init();
    }
}(jQuery));