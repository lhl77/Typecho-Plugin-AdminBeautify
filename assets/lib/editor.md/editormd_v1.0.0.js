(function () {
    var cfg = window.__AB_CONFIG__ || {};
    if (String(cfg.editorVditor) !== "3") return;

    var AB_EDITORMD_ADAPTER_VERSION = "1.0.0";

    function isWritePage() {
        var href = String(window.location.href || "");
        return href.indexOf("write-post.php") !== -1 || href.indexOf("write-page.php") !== -1;
    }

    function normalizeLibPath() {
        var path = String(cfg.editorMdLibPath || "");
        if (!path) {
            var base = String(cfg.editorMdAssetBaseUrl || "/usr/plugins/AdminBeautify/assets/lib/editor.md").replace(/\/+$/, "");
            path = base + "/lib/";
        }
        if (path.slice(-1) !== "/") path += "/";
        return path;
    }

    function detectAssetBaseUrl() {
        var fromCfg = String(cfg.editorMdAssetBaseUrl || "").replace(/\/+$/, "");
        if (fromCfg) return fromCfg;

        var scripts = document.getElementsByTagName("script");
        for (var i = scripts.length - 1; i >= 0; i--) {
            var src = String(scripts[i].getAttribute("src") || "");
            if (!src) continue;
            if (src.indexOf("/assets/lib/editor.md/") !== -1) {
                return src
                    .replace(/\?.*$/, "")
                    .replace(/#.*$/, "")
                    .replace(/\/(editormd_v1\.0\.0|editormd|local-emoji-loader)\.js$/i, "")
                    .replace(/\/plugins\/[^\/]+\/[^\/]+\.js$/i, "")
                    .replace(/\/+$/, "");
            }
        }

        return "/usr/plugins/AdminBeautify/assets/lib/editor.md";
    }

    function normalizeAssetBaseUrl() {
        var base = detectAssetBaseUrl();
        base = base.replace(/\/usr\/plugin\//i, '/usr/plugins/');
        if (/^https?:\/\//i.test(base) && window.location && window.location.protocol === "https:") {
            base = base.replace(/^http:\/\//i, "https://");
        } else if (!/^https?:\/\//i.test(base)) {
            if (base.slice(0, 2) === "//") {
                base = (window.location ? window.location.protocol : "https:") + base;
            } else {
                if (base.charAt(0) !== "/") base = "/" + base;
                if (window.location && window.location.origin) {
                    base = window.location.origin + base;
                }
            }
        }
        return base;
    }

    function getLocalEmojiBasePath() {
        return normalizeAssetBaseUrl() + "/emojis";
    }

    function getLocalEmojiProxyUrl(hexSeq, type) {
        // 使用本地文件而不是代理
        type = type || "twemoji";
        var basePath = getLocalEmojiBasePath();
        var hex = String(hexSeq || "").toLowerCase();
        
        if (type === "github") {
            // Github emoji 使用名称
            return basePath + "/github-emoji/" + encodeURIComponent(hex) + ".png";
        }
        
        // Twemoji 使用 hex 码
        return basePath + "/twemoji/" + encodeURIComponent(hex) + ".png";
    }

    window.__abHandleEmojiImgError = function (imgEl) {
        if (!imgEl) return false;
        var fallbackText = imgEl.getAttribute("data-ab-fallback-text") || imgEl.getAttribute("alt") || "";
        if (imgEl.parentNode) imgEl.parentNode.replaceChild(document.createTextNode(fallbackText), imgEl);
        return false;
    };

    function buildEmojiFallbackSpan(text) {
        var safe = String(text || '');
        return '<span class="ab-emoji-text">' + safe + '</span>';
    }

    function dispatchInput(el) {
        if (!el) return;
        try {
            var evt = document.createEvent("Event");
            evt.initEvent("input", true, true);
            el.dispatchEvent(evt);
        } catch (e) {}
    }

    function hideLegacyBars() {
        document.body.classList.add("ab-editormd-active");
        var legacy = document.querySelectorAll("#wmd-button-bar, #wmd-button-row, #wmd-preview, #wmd-editarea");
        for (var i = 0; i < legacy.length; i++) {
            legacy[i].classList.add("ab-editormd-legacy-bar");
            legacy[i].style.display = "none";
        }
    }

    function applyThemeClass() {
        var outer = document.getElementById("ab-editormd-outer");
        var dark = document.documentElement.getAttribute("data-theme") === "dark";
        if (outer) {
            if (dark) outer.classList.add("ab-editormd-dark");
            else outer.classList.remove("ab-editormd-dark");
        }
    }

    function createMount(textarea) {
        var oldOuter = document.getElementById("ab-editormd-outer");
        if (oldOuter && oldOuter.parentNode) oldOuter.parentNode.removeChild(oldOuter);

        // 外层容器（包含 card）
        var outer = document.createElement("div");
        outer.id = "ab-editormd-outer";

        // 编辑器卡片
        var wrap = document.createElement("div");
        wrap.id = "ab-editormd-wrap";

        // 加载动画（仿 Vditor loading overlay）
        var loader = document.createElement("div");
        loader.className = "ab-editormd-loader";
        loader.innerHTML =
            "<div class=\"ab-editormd-loader__pulse\"></div>" +
            "<div class=\"ab-editormd-loader__title\">正在初始化编辑器</div>";

        // editormd 挂载点
        var mount = document.createElement("div");
        mount.id = "ab-editormd";
        mount.className = "ab-editormd-host";
        var hiddenTA = document.createElement("textarea");
        hiddenTA.style.display = "none";
        hiddenTA.id = "ab-editormd-textarea";
        hiddenTA.value = String(textarea.value || "");
        mount.appendChild(hiddenTA);

        wrap.appendChild(loader);
        wrap.appendChild(mount);
        outer.appendChild(wrap);

        var anchor = document.getElementById("wmd-editarea");
        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(outer, anchor);
        } else if (textarea && textarea.parentNode) {
            textarea.parentNode.insertBefore(outer, textarea);
        }

        return mount;
    }

    // ─── MD3 工具栏图标注入 ──────────────────────────────────────────────
    var TOOLBAR_ICON_MAP = {
        'undo':              'undo',
        'redo':              'redo',
        'bold':              'format_bold',
        'del':               'format_strikethrough',
        'italic':            'format_italic',
        'quote':             'format_quote',
        'uppercase':         'text_fields',
        'lowercase':         'text_format',
        'ucwords':           'title',
        'list-ul':           'format_list_bulleted',
        'list-ol':           'format_list_numbered',
        'hr':                'horizontal_rule',
        'link':              'link',
        'anchor':            'anchor',
        'image':             'image',
        'code':              'code',
        'preformatted-text': 'article',
        'code-block':        'integration_instructions',
        'table':             'table_chart',
        'datetime':          'schedule',
        'emoji':             'mood',
        'html-entities':     'language',
        'pagebreak':         'horizontal_split',
        'goto-line':         'keyboard_tab',
        'watch':             'visibility',
        'preview':           'open_in_full',
        'fullscreen':        'fullscreen',
        'clear':             'delete_sweep',
        'search':            'search',
        'help':              'help_outline',
        'info':              'info'
    };
    var MD_ICON_CLASS = 'material-icons-round ab-md-icon';
    var H_LABELS = { h1:'H1', h2:'H2', h3:'H3', h4:'H4', h5:'H5', h6:'H6' };

    function injectMd3Icons() {
        var toolbar = document.querySelector('#ab-editormd-wrap .editormd-toolbar');
        if (!toolbar) return;
        var faEls = toolbar.querySelectorAll('li > a > i.fa, li > a > i');
        for (var i = 0; i < faEls.length; i++) {
            var el = faEls[i];
            var key = el.getAttribute('name') || '';
            var btn = el.parentNode;
            if (!btn || btn.tagName !== 'A') continue;
            if (btn.querySelector('.ab-md-icon') || btn.querySelector('.ab-h-label')) continue;
            if (H_LABELS[key]) {
                var hSpan = document.createElement('span');
                hSpan.className = 'ab-h-label';
                hSpan.textContent = H_LABELS[key];
                btn.insertBefore(hSpan, btn.firstChild);
                btn.classList.add('ab-iconized');
            } else if (TOOLBAR_ICON_MAP[key]) {
                var iconSpan = document.createElement('span');
                iconSpan.className = MD_ICON_CLASS;
                iconSpan.textContent = TOOLBAR_ICON_MAP[key];
                btn.insertBefore(iconSpan, btn.firstChild);
                btn.classList.add('ab-iconized');
            }
        }
    }

    // ─── GitHub Emoji 名称 → Unicode 字符映射表 ──────────────────────────
    window.__AB_GHE = (function () {
        var H = {
            // 表情/脸
            smile:'1f604',laughing:'1f606',blush:'1f60a',smiley:'1f603',relaxed:'263a',
            smirk:'1f60f',heart_eyes:'1f60d',kissing_heart:'1f618',kissing_closed_eyes:'1f61a',
            flushed:'1f633',relieved:'1f60c',satisfied:'1f606',grin:'1f601',wink:'1f609',
            stuck_out_tongue_winking_eye:'1f61c',stuck_out_tongue_closed_eyes:'1f61d',
            grinning:'1f600',kissing:'1f617',kissing_smiling_eyes:'1f619',
            stuck_out_tongue:'1f61b',sleeping:'1f634',worried:'1f61f',frowning:'1f626',
            anguished:'1f627',open_mouth:'1f62e',grimacing:'1f62c',confused:'1f615',
            hushed:'1f62f',expressionless:'1f611',unamused:'1f612',sweat_smile:'1f605',
            sweat:'1f613',disappointed_relieved:'1f625',weary:'1f629',pensive:'1f614',
            disappointed:'1f61e',confounded:'1f616',fearful:'1f628',cold_sweat:'1f630',
            persevere:'1f623',cry:'1f622',sob:'1f62d',joy:'1f602',astonished:'1f632',
            scream:'1f631',tired_face:'1f62b',angry:'1f620',rage:'1f621',triumph:'1f624',
            sleepy:'1f62a',yum:'1f60b',mask:'1f637',sunglasses:'1f60e',dizzy_face:'1f635',
            imp:'1f47f',smiling_imp:'1f608',neutral_face:'1f610',no_mouth:'1f636',
            innocent:'1f607',alien:'1f47d',
            // 心/符号
            yellow_heart:'1f49b',blue_heart:'1f499',purple_heart:'1f49c',heart:'2764',
            green_heart:'1f49a',broken_heart:'1f494',heartbeat:'1f493',heartpulse:'1f497',
            two_hearts:'1f495',revolving_hearts:'1f49e',cupid:'1f498',sparkling_heart:'1f496',
            sparkles:'2728',star:'2b50',star2:'1f31f',dizzy:'1f4ab',boom:'1f4a5',
            collision:'1f4a5',anger:'1f4a2',exclamation:'2757',question:'2753',
            grey_exclamation:'2755',grey_question:'2754',zzz:'1f4a4',dash:'1f4a8',
            sweat_drops:'1f4a6',notes:'1f3b6',musical_note:'1f3b5',fire:'1f525',
            hankey:'1f4a9',poop:'1f4a9',shit:'1f4a9',
            // 手势/人
            thumbsup:'1f44d',thumbsdown:'1f44e',ok_hand:'1f44c',punch:'1f44a',
            facepunch:'1f44a',fist:'270a',v:'270c',wave:'1f44b',hand:'270b',
            raised_hand:'270b',open_hands:'1f450',point_up:'261d',point_down:'1f447',
            point_left:'1f448',point_right:'1f449',raised_hands:'1f64c',pray:'1f64f',
            point_up_2:'1f446',clap:'1f44f',muscle:'1f4aa',fu:'1f595',walking:'1f6b6',
            runner:'1f3c3',running:'1f3c3',couple:'1f46b',family:'1f46a',
            two_men_holding_hands:'1f46c',two_women_holding_hands:'1f46d',dancer:'1f483',
            dancers:'1f46f',ok_woman:'1f646',no_good:'1f645',information_desk_person:'1f481',
            raising_hand:'1f64b',bride_with_veil:'1f470',person_with_pouting_face:'1f64e',
            person_frowning:'1f64d',bow:'1f647',couplekiss:'1f48f',couple_with_heart:'1f491',
            massage:'1f486',haircut:'1f487',nail_care:'1f485',boy:'1f466',girl:'1f467',
            woman:'1f469',man:'1f468',baby:'1f476',older_woman:'1f475',older_man:'1f474',
            person_with_blond_hair:'1f471',man_with_gua_pi_mao:'1f472',man_with_turban:'1f473',
            construction_worker:'1f477',cop:'1f46e',angel:'1f47c',princess:'1f478',
            smiley_cat:'1f63a',smile_cat:'1f638',heart_eyes_cat:'1f63b',kissing_cat:'1f63d',
            smirk_cat:'1f63c',scream_cat:'1f640',crying_cat_face:'1f63f',joy_cat:'1f639',
            pouting_cat:'1f63e',japanese_ogre:'1f479',japanese_goblin:'1f47a',
            see_no_evil:'1f648',hear_no_evil:'1f649',speak_no_evil:'1f64a',
            guardsman:'1f482',skull:'1f480',feet:'1f43e',lips:'1f444',kiss:'1f48b',
            droplet:'1f4a7',ear:'1f442',eyes:'1f440',nose:'1f443',tongue:'1f445',
            love_letter:'1f48c',bust_in_silhouette:'1f464',busts_in_silhouette:'1f465',
            speech_balloon:'1f4ac',thought_balloon:'1f4ad',
            // 自然/动物
            sunny:'2600',umbrella:'2602',cloud:'2601',snowflake:'2744',snowman:'26c4',
            zap:'26a1',cyclone:'1f300',foggy:'1f301',ocean:'1f30a',cat:'1f431',
            dog:'1f436',mouse:'1f42d',hamster:'1f439',rabbit:'1f430',wolf:'1f43a',
            frog:'1f438',tiger:'1f42f',koala:'1f428',bear:'1f43b',pig:'1f437',
            pig_nose:'1f43d',cow:'1f42e',boar:'1f417',monkey_face:'1f435',monkey:'1f412',
            horse:'1f434',racehorse:'1f40e',camel:'1f42b',sheep:'1f411',elephant:'1f418',
            panda_face:'1f43c',snake:'1f40d',bird:'1f426',baby_chick:'1f424',
            hatched_chick:'1f425',hatching_chick:'1f423',chicken:'1f414',penguin:'1f427',
            turtle:'1f422',bug:'1f41b',honeybee:'1f41d',ant:'1f41c',beetle:'1f41e',
            snail:'1f40c',octopus:'1f419',tropical_fish:'1f420',fish:'1f41f',whale:'1f433',
            whale2:'1f40b',dolphin:'1f42c',cow2:'1f404',ram:'1f40f',rat:'1f400',
            water_buffalo:'1f403',tiger2:'1f405',rabbit2:'1f407',dragon:'1f409',
            goat:'1f410',rooster:'1f413',dog2:'1f415',pig2:'1f416',mouse2:'1f401',
            ox:'1f402',dragon_face:'1f432',blowfish:'1f421',crocodile:'1f40a',
            dromedary_camel:'1f42a',leopard:'1f406',cat2:'1f408',poodle:'1f429',
            paw_prints:'1f43e',bouquet:'1f490',cherry_blossom:'1f338',tulip:'1f337',
            four_leaf_clover:'1f340',rose:'1f339',sunflower:'1f33b',hibiscus:'1f33a',
            maple_leaf:'1f341',leaves:'1f343',fallen_leaf:'1f342',herb:'1f33f',
            mushroom:'1f344',cactus:'1f335',palm_tree:'1f334',evergreen_tree:'1f332',
            deciduous_tree:'1f333',chestnut:'1f330',seedling:'1f331',blossom:'1f33c',
            ear_of_rice:'1f33e',shell:'1f41a',globe_with_meridians:'1f310',
            sun_with_face:'1f31e',full_moon_with_face:'1f31d',new_moon_with_face:'1f31a',
            new_moon:'1f311',waxing_crescent_moon:'1f312',first_quarter_moon:'1f313',
            waxing_gibbous_moon:'1f314',full_moon:'1f315',waning_gibbous_moon:'1f316',
            last_quarter_moon:'1f317',waning_crescent_moon:'1f318',
            last_quarter_moon_with_face:'1f31c',first_quarter_moon_with_face:'1f31b',
            moon:'1f319',earth_africa:'1f30d',earth_americas:'1f30e',earth_asia:'1f30f',
            volcano:'1f30b',milky_way:'1f30c',partly_sunny:'26c5',
            // 物品
            ghost:'1f47b',santa:'1f385',christmas_tree:'1f384',gift:'1f381',bell:'1f514',
            no_bell:'1f515',tada:'1f389',confetti_ball:'1f38a',balloon:'1f388',
            crystal_ball:'1f52e',camera:'1f4f7',video_camera:'1f4f9',movie_camera:'1f3a5',
            computer:'1f4bb',tv:'1f4fa',iphone:'1f4f1',phone:'260e',telephone_receiver:'1f4de',
            hourglass:'231b',hourglass_flowing_sand:'23f3',alarm_clock:'23f0',
            satellite:'1f4e1',mag:'1f50d',mag_right:'1f50e',unlock:'1f513',lock:'1f512',
            key:'1f511',bulb:'1f4a1',flashlight:'1f526',electric_plug:'1f50c',battery:'1f50b',
            email:'1f4e7',mailbox:'1f4eb',postbox:'1f4ee',shower:'1f6bf',toilet:'1f6bd',
            wrench:'1f527',nut_and_bolt:'1f529',hammer:'1f528',seat:'1f4ba',moneybag:'1f4b0',
            dollar:'1f4b5',euro:'1f4b6',credit_card:'1f4b3',money_with_wings:'1f4b8',
            envelope:'2709',package:'1f4e6',door:'1f6aa',smoking:'1f6ac',bomb:'1f4a3',
            gun:'1f52b',pill:'1f48a',syringe:'1f489',page_facing_up:'1f4c4',
            bar_chart:'1f4ca',chart_with_upwards_trend:'1f4c8',scroll:'1f4dc',
            clipboard:'1f4cb',calendar:'1f4c5',date:'1f4c5',file_folder:'1f4c1',
            open_file_folder:'1f4c2',scissors:'2702',pushpin:'1f4cc',paperclip:'1f4ce',
            pencil2:'270f',closed_book:'1f4d5',green_book:'1f4d7',blue_book:'1f4d8',
            orange_book:'1f4d9',notebook:'1f4d3',books:'1f4da',bookmark:'1f516',
            microscope:'1f52c',telescope:'1f52d',newspaper:'1f4f0',
            football:'1f3c8',basketball:'1f3c0',soccer:'26bd',baseball:'26be',
            tennis:'1f3be',bowling:'1f3b3',golf:'26f3',gem:'1f48e',ring:'1f48d',
            trophy:'1f3c6',musical_score:'1f3bc',musical_keyboard:'1f3b9',violin:'1f3bb',
            space_invader:'1f47e',video_game:'1f3ae',game_die:'1f3b2',dart:'1f3af',
            clapper:'1f3ac',art:'1f3a8',microphone:'1f3a4',headphones:'1f3a7',
            trumpet:'1f3ba',saxophone:'1f3b7',guitar:'1f3b8',lipstick:'1f484',
            boot:'1f462',tshirt:'1f455',necktie:'1f454',dress:'1f457',jeans:'1f456',
            kimono:'1f458',bikini:'1f459',ribbon:'1f380',tophat:'1f3a9',crown:'1f451',
            womans_hat:'1f452',briefcase:'1f4bc',handbag:'1f45c',eyeglasses:'1f453',
            coffee:'2615',tea:'1f375',sake:'1f376',baby_bottle:'1f37c',beer:'1f37a',
            beers:'1f37b',cocktail:'1f378',tropical_drink:'1f379',wine_glass:'1f377',
            fork_and_knife:'1f374',pizza:'1f355',hamburger:'1f354',fries:'1f35f',
            poultry_leg:'1f357',meat_on_bone:'1f356',spaghetti:'1f35d',curry:'1f35b',
            bento:'1f371',sushi:'1f363',rice_ball:'1f359',rice:'1f35a',ramen:'1f35c',
            stew:'1f372',egg:'1f373',bread:'1f35e',doughnut:'1f369',icecream:'1f366',
            ice_cream:'1f368',shaved_ice:'1f367',birthday:'1f382',cake:'1f370',
            cookie:'1f36a',chocolate_bar:'1f36b',candy:'1f36c',lollipop:'1f36d',
            honey_pot:'1f36f',apple:'1f34e',green_apple:'1f34f',tangerine:'1f34a',
            lemon:'1f34b',cherries:'1f352',grapes:'1f347',watermelon:'1f349',
            strawberry:'1f353',peach:'1f351',melon:'1f348',banana:'1f34c',pear:'1f350',
            pineapple:'1f34d',sweet_potato:'1f360',eggplant:'1f346',tomato:'1f345',
            corn:'1f33d',
            // 地点/交通
            house:'1f3e0',hospital:'1f3e5',bank:'1f3e6',school:'1f3eb',factory:'1f3ed',
            izakaya_lantern:'1f3ee',japanese_castle:'1f3ef',european_castle:'1f3f0',
            sunrise_over_mountains:'1f304',sunrise:'1f305',city_sunrise:'1f307',
            city_sunset:'1f306',night_with_stars:'1f303',bridge_at_night:'1f309',
            rainbow:'1f308',ferris_wheel:'1f3a1',roller_coaster:'1f3a2',
            carousel_horse:'1f3a0',statue_of_liberty:'1f5fd',moyai:'1f5ff',
            mount_fuji:'1f5fb',car:'1f697',taxi:'1f695',bus:'1f68c',trolleybus:'1f68e',
            police_car:'1f693',ambulance:'1f691',fire_engine:'1f692',minibus:'1f690',
            truck:'1f69a',train:'1f68b',train2:'1f686',bullettrain_side:'1f684',
            bullettrain_front:'1f685',metro:'1f687',station:'1f689',helicopter:'1f681',
            airplane:'2708',boat:'26f5',rocket:'1f680',anchor:'2693',construction:'1f6a7'
        };
        // 支持 :+1: :‑1: 特殊键
        H['+1'] = H.thumbsup;
        H['-1'] = H.thumbsdown;
        window.__AB_GHE_HEX = H;

        var r = {};
        for (var k in H) {
            try { r[k] = String.fromCodePoint(parseInt(H[k], 16)); } catch (e) {}
        }
        return r;
    }());

    function toUnicodeFromHexSequence(hexSeq) {
        var text = String(hexSeq || '').toLowerCase();
        if (!text) return '';
        var parts = text.split('-');
        var cps = [];
        for (var i = 0; i < parts.length; i++) {
            var n = parseInt(parts[i], 16);
            if (!isNaN(n)) cps.push(n);
        }
        if (!cps.length || !String.fromCodePoint) return '';
        try {
            return String.fromCodePoint.apply(String, cps);
        } catch (e) {
            return '';
        }
    }

    function emojiTokenToHtml(rawToken) {
        var token = String(rawToken || '');
        if (!token) return '';
        if (token === '\\+1') token = '+1';

        if (/^fa-[\w-]+$/.test(token)) {
            return '<i class="fa ' + token + ' fa-emoji" title="' + token.replace('fa-', '') + '"></i>';
        }

        if (/^editormd-logo(?:-\w+)?$/.test(token)) {
            return '<i class="' + token + '" title="Editor.md logo (' + token + ')"></i>';
        }

        if (token.indexOf('tw-') === 0) {
            var tw = token.replace(/^tw-/, '');
            var twChar = toUnicodeFromHexSequence(tw);
            if (twChar) {
                var twSrc = getLocalEmojiProxyUrl(tw, 'twemoji');
                return '<img class="ab-emoji-preview-img" src="' + twSrc + '" alt="twemoji-' + tw + '" title=":' + token + ':" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-ab-emoji-hex="' + tw + '" data-ab-fallback-text="' + twChar + '" onerror="if(this.parentNode){var txt=this.getAttribute(\'data-ab-fallback-text\')|| this.getAttribute(\'alt\');this.parentNode.replaceChild(document.createTextNode(txt),this);}">';
            }
            return '<span class="ab-emoji-text" title=":' + token + ':">:' + token + ':</span>';
        }

        var ghe = (window.__AB_GHE && window.__AB_GHE[token]) ? window.__AB_GHE[token] : '';
        var gheFallback = ghe || (':' + token + ':');
        var gheSrc = getLocalEmojiProxyUrl(token, 'github');
        return '<img class="ab-emoji-preview-img" src="' + gheSrc + '" alt="emoji-' + token + '" title=":' + token + ':" loading="lazy" decoding="async" referrerpolicy="no-referrer" data-ab-emoji-name="' + token + '" data-ab-fallback-text="' + gheFallback + '" onerror="if(this.parentNode){var txt=this.getAttribute(\'data-ab-fallback-text\')|| this.getAttribute(\'alt\');var span=document.createElement(\'span\');span.className=\'ab-emoji-text\';span.textContent=txt;this.parentNode.replaceChild(span,this);}">';
    }

    function patchMarkedEmojiRenderer() {
        if (window.__abEditorMdMarkedPatched) return;
        if (!window.editormd || typeof window.editormd.markedRenderer !== 'function') return;

        var originalMarkedRenderer = window.editormd.markedRenderer;

        window.editormd.markedRenderer = function(markdownToC, options) {
            var renderer = originalMarkedRenderer.apply(this, arguments);
            if (!renderer || typeof renderer.emoji !== 'function') return renderer;

            renderer.emoji = function(text) {
                var source = String(text || '');
                if (!source) return source;

                var regexs = window.editormd.regexs || {};
                var dtReg = regexs.emojiDatetime || /(\d{1,2}:\d{1,2}:\d{1,2})/g;
                var emojiReg = regexs.emoji || /:([\w\+-]+):/g;

                if (options && options.emoji === false) return source;

                source = source.replace(dtReg, function(match) {
                    return match.replace(/:/g, '&#58;');
                });

                return source.replace(emojiReg, function(_, token) {
                    return emojiTokenToHtml(token);
                });
            };

            return renderer;
        };

        window.__abEditorMdMarkedPatched = true;
    }

    // ─── Emoji 预览后处理：镜像优先，字符回退 ──────────────────────────────
    function fixEmojiImgs(container) {
        if (!container) return;
        var imgs = container.querySelectorAll('img.emoji');
        for (var i = 0; i < imgs.length; i++) {
            var img = imgs[i];
            if (img.classList.contains('ab-emoji-preview-img')) continue;
            var alt = img.getAttribute('alt') || '';
            var twMatch = alt.match(/^twemoji-([0-9a-f-]+)$/i) || alt.match(/^:tw-([0-9a-f-]+):?$/i);
            if (twMatch) {
                img.src = getLocalEmojiProxyUrl(twMatch[1], 'twemoji');
                img.className = 'ab-emoji-preview-img';
                img.setAttribute('data-ab-emoji-hex', String(twMatch[1]).toLowerCase());
                img.setAttribute('data-ab-fallback-text', toUnicodeFromHexSequence(twMatch[1]) || alt);
                img.onerror = function () {
                    if(this.parentNode){var txt=this.getAttribute('data-ab-fallback-text')||this.getAttribute('alt');this.parentNode.replaceChild(document.createTextNode(txt),this);}
                };
                continue;
            } else {
                var name = alt.replace(/^:|:$/g, '');
                if (name) {
                    img.src = getLocalEmojiProxyUrl(name, 'github');
                    img.className = 'ab-emoji-preview-img';
                    img.setAttribute('data-ab-emoji-name', name);
                    img.setAttribute('data-ab-fallback-text', (window.__AB_GHE && window.__AB_GHE[name]) ? window.__AB_GHE[name] : (':' + name + ':'));
                    img.onerror = function () {
                        if(this.parentNode){var txt=this.getAttribute('data-ab-fallback-text')||this.getAttribute('alt');var span=document.createElement('span');span.className='ab-emoji-text';span.textContent=txt;this.parentNode.replaceChild(span,this);}
                    };
                    continue;
                }
            }
        }
    }

    function patchDialogLangFallbacks(editor) {
        if (!editor || !editor.lang) return;
        editor.lang.dialog = editor.lang.dialog || {};
        editor.lang.dialog.codeBlock = editor.lang.dialog.codeBlock || {};
        editor.lang.dialog.preformattedText = editor.lang.dialog.preformattedText || {};

        var cb = editor.lang.dialog.codeBlock;
        var pf = editor.lang.dialog.preformattedText;

        if (!cb.title || String(cb.title).toLowerCase() === 'undefined') cb.title = '添加代码块';
        if (!cb.selectLabel || String(cb.selectLabel).toLowerCase() === 'undefined') cb.selectLabel = '代码语言：';
        if (!cb.selectDefaultText || String(cb.selectDefaultText).toLowerCase() === 'undefined') cb.selectDefaultText = '请选择代码语言';
        if (!cb.otherLanguage || String(cb.otherLanguage).toLowerCase() === 'undefined') cb.otherLanguage = '其他语言';
        if (!cb.unselectedLanguageAlert || String(cb.unselectedLanguageAlert).toLowerCase() === 'undefined') cb.unselectedLanguageAlert = '错误：请选择代码所属的语言类型。';
        if (!cb.codeEmptyAlert || String(cb.codeEmptyAlert).toLowerCase() === 'undefined') cb.codeEmptyAlert = '错误：请填写代码内容。';
        if (!cb.placeholder || String(cb.placeholder).toLowerCase() === 'undefined') cb.placeholder = '请输入代码内容...';

        if (!pf.title || String(pf.title).toLowerCase() === 'undefined') pf.title = '添加预格式文本';
        if (!pf.emptyAlert || String(pf.emptyAlert).toLowerCase() === 'undefined') pf.emptyAlert = '错误：请填写预格式文本或代码的内容。';
        if (!pf.placeholder || String(pf.placeholder).toLowerCase() === 'undefined') pf.placeholder = '请输入预格式文本内容...';
    }

    function patchPreviewTopOverlap(editor) {
        if (!editor || !editor.editor) return;
        var root = editor.editor[0] || editor.editor;
        if (!root || !root.querySelector) return;
        var toolbar = root.querySelector('.editormd-toolbar');
        var preview = root.querySelector('.editormd-preview');
        if (!toolbar || !preview) return;
        var h = toolbar.offsetHeight || 0;
        preview.style.top = h + 'px';
        if (editor.state && editor.state.preview) {
            preview.style.height = 'calc(100% - ' + h + 'px)';
        }
    }

    function hookPreviewTopOverlap(editor) {
        if (!editor || editor.__abPreviewPatched) return;
        editor.__abPreviewPatched = true;

        var rawPreviewing = editor.previewing;
        var rawPreviewed = editor.previewed;
        var rawResize = editor.resize;

        if (typeof rawPreviewing === 'function') {
            editor.previewing = function () {
                var ret = rawPreviewing.apply(this, arguments);
                setTimeout(function () { patchPreviewTopOverlap(editor); }, 0);
                return ret;
            };
        }
        if (typeof rawPreviewed === 'function') {
            editor.previewed = function () {
                var ret = rawPreviewed.apply(this, arguments);
                setTimeout(function () { patchPreviewTopOverlap(editor); }, 0);
                return ret;
            };
        }
        if (typeof rawResize === 'function') {
            editor.resize = function () {
                var ret = rawResize.apply(this, arguments);
                patchPreviewTopOverlap(editor);
                return ret;
            };
        }
    }

    function removeFullscreenButton() {
        var toolbar = document.querySelector('#ab-editormd-wrap .editormd-toolbar');
        if (!toolbar) return;
        var nodes = toolbar.querySelectorAll('li > a > i[name="fullscreen"], li > a[title*="全屏"], li > a[title*="fullscreen" i]');
        for (var i = 0; i < nodes.length; i++) {
            var anchor = nodes[i].tagName === 'A' ? nodes[i] : nodes[i].parentNode;
            if (anchor && anchor.parentNode && anchor.parentNode.tagName === 'LI') {
                anchor.parentNode.parentNode.removeChild(anchor.parentNode);
            }
        }
    }

    function watchPreviewEmoji(editor) {
        var preview = document.querySelector('#ab-editormd .editormd-preview-container');
        if (!preview) return;
        fixEmojiImgs(preview);
        if (window.__abEditorMdEmojiObserver) return;
        var obs = new MutationObserver(function () { fixEmojiImgs(preview); });
        obs.observe(preview, { childList: true, subtree: true });
        window.__abEditorMdEmojiObserver = obs;
    }

    function watchToolbarIcons() {
        if (window.__abEditorMdToolbarObserver) return;
        var toolbar = document.querySelector('#ab-editormd-wrap .editormd-toolbar');
        if (!toolbar) return;

        var queued = false;
        var refresh = function () {
            queued = false;
            injectMd3Icons();
            removeFullscreenButton();
        };

        var obs = new MutationObserver(function(muts) {
            var changed = false;
            for (var i = 0; i < muts.length; i++) {
                var m = muts[i];
                if (m.type === 'childList') {
                    changed = true;
                    break;
                }
                if (m.type === 'attributes' && m.attributeName === 'class') {
                    changed = true;
                    break;
                }
            }
            if (changed && !queued) {
                queued = true;
                setTimeout(refresh, 0);
            }
        });

        obs.observe(toolbar, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
        window.__abEditorMdToolbarObserver = obs;
    }
    var __abEditorMdScrim = null;

    function ensureScrim() {
        if (!__abEditorMdScrim) {
            __abEditorMdScrim = document.createElement('div');
            __abEditorMdScrim.id = 'ab-editormd-scrim';
            document.body.appendChild(__abEditorMdScrim);
        }
        __abEditorMdScrim.classList.add('ab-editormd-scrim--visible');
    }

    function removeScrimIfNoDialogs() {
        var dlgs = document.querySelectorAll('.editormd-dialog');
        for (var i = 0; i < dlgs.length; i++) {
            if (dlgs[i].style.display === 'block') return;
        }
        if (__abEditorMdScrim) {
            __abEditorMdScrim.classList.remove('ab-editormd-scrim--visible');
        }
    }

    function patchInfoDialog(dlg) {
        var container = dlg.querySelector('.editormd-dialog-container');
        if (!container || container.getAttribute('data-ab-patched')) return;
        container.setAttribute('data-ab-patched', '1');
        var edVer = (window.editormd && window.editormd.version) ? window.editormd.version : '';
        container.innerHTML = '';
        container.style.cssText = 'padding:0;background:transparent;';

        // 图标展示区
        var hero = document.createElement('div');
        hero.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:4px;padding:8px 24px 16px;background:var(--md-secondary-container,#e8def8);';

        var iconWrap = document.createElement('div');
        iconWrap.style.cssText = 'width:56px;height:56px;border-radius:50%;background:var(--md-on-secondary-container,#1d192b);display:flex;align-items:center;justify-content:center;margin:12px 0 8px;';
        var iconEl = document.createElement('span');
        iconEl.className = MD_ICON_CLASS;
        iconEl.textContent = 'edit_note';
        iconEl.style.cssText = 'font-size:32px;color:var(--md-secondary-container,#e8def8);';
        iconWrap.appendChild(iconEl);

        var titleEl = document.createElement('div');
        titleEl.style.cssText = 'font-size:22px;font-weight:700;color:var(--md-on-secondary-container,#1d192b);letter-spacing:-.3px;';
        titleEl.textContent = 'AB Editor.md';

        var verEl = document.createElement('div');
        verEl.style.cssText = 'font-size:12px;font-weight:500;color:var(--md-on-surface-variant,#49454f);padding:2px 10px;border-radius:20px;margin-top:2px;';
        verEl.textContent = 'v' + AB_EDITORMD_ADAPTER_VERSION;

        var descEl = document.createElement('p');
        descEl.style.cssText = 'font-size:13px;color:var(--md-on-surface-variant,#49454f);margin:6px 0 0;';
        descEl.textContent = 'AdminBeautify 编辑器适配组件';

        hero.appendChild(iconWrap);
        hero.appendChild(titleEl);
        hero.appendChild(verEl);
        hero.appendChild(descEl);
        container.appendChild(hero);

        // 链接区
        var links = document.createElement('div');
        links.style.cssText = 'padding:16px 24px 8px;display:flex;flex-direction:column;gap:8px;';

        var projLink = document.createElement('a');
        projLink.href = 'https://github.com/lhl77/Typecho-Plugin-AdminBeautify';
        projLink.target = '_blank';
        projLink.rel = 'noopener';
        projLink.style.cssText = 'display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;background:var(--md-surface-container,#f3edf7);text-decoration:none;color:var(--md-on-surface,#1c1b1f);font-size:13px;font-weight:500;';
        var projIcon = document.createElement('span');
        projIcon.className = MD_ICON_CLASS;
        projIcon.textContent = 'code';
        projIcon.style.cssText = 'color:var(--md-primary,#6750a4);font-size:20px;';
        var projText = document.createElement('span');
        projText.textContent = 'github.com/lhl77/Typecho-Plugin-AdminBeautify';
        projLink.appendChild(projIcon);
        projLink.appendChild(projText);
        links.appendChild(projLink);

        if (edVer) {
            var edLink = document.createElement('a');
            edLink.href = 'https://pandao.github.io/editor.md/';
            edLink.target = '_blank';
            edLink.rel = 'noopener';
            edLink.style.cssText = 'display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;background:var(--md-surface-container,#f3edf7);text-decoration:none;color:var(--md-on-surface,#1c1b1f);font-size:13px;font-weight:500;';
            var edIcon = document.createElement('span');
            edIcon.className = MD_ICON_CLASS;
            edIcon.textContent = 'library_books';
            edIcon.style.cssText = 'color:var(--md-tertiary,#7d5260);font-size:20px;';
            var edText = document.createElement('span');
            edText.textContent = '基于 Editor.md v' + edVer;
            edLink.appendChild(edIcon);
            edLink.appendChild(edText);
            links.appendChild(edLink);
        }
        container.appendChild(links);
    }

    function patchDialogFallbackTitle(dlg) {
        if (!dlg) return;
        var titleEl = dlg.querySelector('.editormd-dialog-title');
        if (!titleEl) return;
        var text = String(titleEl.textContent || '').trim().toLowerCase();
        if (text && text !== 'undefined') return;

        if (dlg.classList.contains('editormd-code-block-dialog')) {
            titleEl.textContent = '添加代码块';
            return;
        }
        if (dlg.classList.contains('editormd-preformatted-text-dialog')) {
            titleEl.textContent = '添加预格式文本';
            return;
        }
    }

    function patchDialog(dlg) {
        if (!dlg) return;
        patchDialogFallbackTitle(dlg);

        if (dlg.parentNode !== document.body) {
            document.body.appendChild(dlg);
        }

        var vw = window.innerWidth || document.documentElement.clientWidth || 1280;
        var vh = window.innerHeight || document.documentElement.clientHeight || 720;
        dlg.style.setProperty('position', 'fixed', 'important');
        dlg.style.setProperty('top', Math.round(vh / 2) + 'px', 'important');
        dlg.style.setProperty('left', Math.round(vw / 2) + 'px', 'important');
        dlg.style.setProperty('transform', 'translate(-50%, -50%)', 'important');
        dlg.style.setProperty('margin', '0', 'important');
        dlg.style.setProperty('max-height', 'calc(100vh - 48px)', 'important');
        dlg.style.setProperty('z-index', '99999', 'important');

        if (dlg.classList.contains('ab-md3-patched')) return;
        dlg.classList.add('ab-md3-patched');
        // 关于弹窗：修改内容为 AB 版本信息
        if (dlg.classList.contains('editormd-dialog-info')) {
            patchInfoDialog(dlg);
        }
        var hdr = dlg.querySelector('.editormd-dialog-header');
        if (hdr) hdr.style.cursor = 'default';
        var closeBtn = dlg.querySelector('.editormd-dialog-close');
        if (closeBtn) {
            closeBtn.className = 'editormd-dialog-close';
            closeBtn.innerHTML = '<span class="material-icons-round ab-md-icon">close</span>';
        }
        var container = dlg.querySelector('.editormd-dialog-container');
        if (container) {
            container.style.setProperty('max-height', 'calc(100vh - 180px)', 'important');
            container.style.setProperty('overflow', 'auto', 'important');
        }
    }

    function watchDialogs() {
        if (window.__abEditorMdDlgObserver) return;
        var obs = new MutationObserver(function (muts) {
            for (var i = 0; i < muts.length; i++) {
                var m = muts[i];
                if (m.type === 'attributes' && m.attributeName === 'style') {
                    var el = m.target;
                    if (el.classList && el.classList.contains('editormd-dialog')) {
                        if (el.style.display === 'block') { patchDialog(el); ensureScrim(); }
                        else { removeScrimIfNoDialogs(); }
                    }
                } else if (m.type === 'childList') {
                    for (var j = 0; j < m.addedNodes.length; j++) {
                        var n = m.addedNodes[j];
                        if (n.nodeType === 1 && n.classList && n.classList.contains('editormd-dialog')) {
                            if (n.style.display === 'block') { patchDialog(n); ensureScrim(); }
                        }
                    }
                }
            }
        });
        obs.observe(document.body, { subtree: true, attributes: true, attributeFilter: ['style'], childList: true });
        window.__abEditorMdDlgObserver = obs;

        if (!window.__abEditorMdDlgCenterBound) {
            var recenter = function () {
                var dlgs = document.querySelectorAll('.editormd-dialog');
                for (var i = 0; i < dlgs.length; i++) {
                    if (dlgs[i].style.display === 'block') patchDialog(dlgs[i]);
                }
            };
            window.addEventListener('resize', recenter, { passive: true });
            window.addEventListener('scroll', recenter, { passive: true });
            window.__abEditorMdDlgCenterBound = true;
        }
    }

    function initEditorMd() {
        if (!isWritePage()) return;
        if (window.__abEditorMdInited) return;
        if (typeof window.editormd !== "function") return;

        var textEl = document.getElementById("text");
        if (!textEl || typeof textEl.value === "undefined") return;

        window.__abEditorMdInited = true;
        patchMarkedEmojiRenderer();
        hideLegacyBars();

        var mount = createMount(textEl);
        if (!mount) {
            window.__abEditorMdInited = false;
            return;
        }

        textEl.style.display = "none";

        var syncing = false;
        var form = textEl.form || document.querySelector("form[name=write_post], form[name=write_page]");

        var height = Math.max(500, (window.innerHeight || document.documentElement.clientHeight || 780) - 230);
        var editor = window.editormd("ab-editormd", {
            width: "100%",
            height: height,
            path: normalizeLibPath(),
            markdown: String(textEl.value || ""),
            placeholder: "Start writing markdown...",
            watch: true,
            toolbarAutoFixed: false,
            searchReplace: true,
            emoji: true,
            taskList: true,
            tocm: true,
            tex: true,
            flowChart: true,
            sequenceDiagram: true,
            htmlDecode: "style,script,iframe|on*",
            saveHTMLToTextarea: false,
            toolbarIcons: function () {
                var full = (window.editormd && window.editormd.toolbarModes && window.editormd.toolbarModes.full)
                    ? window.editormd.toolbarModes.full.slice()
                    : [];
                var result = [];
                for (var i = 0; i < full.length; i++) {
                    if (full[i] === 'fullscreen') continue;
                    result.push(full[i]);
                }
                return result;
            },
            onload: function () {
                window.__abEditorMd = this;
                applyThemeClass();

                patchDialogLangFallbacks(this);

                var loader = document.querySelector("#ab-editormd-wrap .ab-editormd-loader");
                if (loader) {
                    loader.classList.add("ab-editormd-loader--leave");
                    setTimeout(function() {
                        if (loader.parentNode) loader.parentNode.removeChild(loader);
                    }, 280);
                }

                injectMd3Icons();
                watchToolbarIcons();
                removeFullscreenButton();
                watchDialogs();
                watchPreviewEmoji(this);
                hookPreviewTopOverlap(this);
                patchPreviewTopOverlap(this);

                if (this.cm && typeof this.cm.on === "function") {
                    this.cm.on("change", function () {
                        if (syncing) return;
                        syncing = true;
                        try {
                            textEl.value = String(window.__abEditorMd.getMarkdown() || "");
                            dispatchInput(textEl);
                        } catch (e) {}
                        syncing = false;
                    });
                }

                if (!textEl._abEditorMdInputBound) {
                    textEl._abEditorMdInputBound = true;
                    textEl.addEventListener("input", function () {
                        if (syncing || !window.__abEditorMd) return;
                        var latest = String(textEl.value || "");
                        var current = "";
                        try {
                            current = String(window.__abEditorMd.getMarkdown() || "");
                        } catch (e) {}
                        if (latest === current) return;
                        syncing = true;
                        try {
                            window.__abEditorMd.setMarkdown(latest);
                        } catch (e2) {}
                        syncing = false;
                    });
                }

                if (form && !form._abEditorMdSubmitBound) {
                    form._abEditorMdSubmitBound = true;
                    form.addEventListener("submit", function () {
                        if (!window.__abEditorMd) return;
                        try {
                            textEl.value = String(window.__abEditorMd.getMarkdown() || "");
                        } catch (e) {}
                    });
                }
            }
        });

        window.__abEditorMd = editor;

        if (!window.__abEditorMdThemeObserver) {
            window.__abEditorMdThemeObserver = new MutationObserver(function () {
                applyThemeClass();
            });
            window.__abEditorMdThemeObserver.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ["data-theme"]
            });
        }
    }

    function boot() {
        var maxRetry = 100;
        var retry = 0;
        var timer = setInterval(function () {
            retry++;
            if (typeof window.editormd === "function" && document.getElementById("text")) {
                clearInterval(timer);
                initEditorMd();
                return;
            }
            if (retry >= maxRetry) {
                clearInterval(timer);
                window.__abEditorMdInited = false;
            }
        }, 80);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }

    if (window.jQuery) {
        window.jQuery(document).on("ab:pageload", function () {
            window.__abEditorMdInited = false;
            boot();
        });
    }
})();
