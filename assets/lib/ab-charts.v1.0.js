/*!
 * ABCharts v1.0 – 轻量级纯 SVG 图表库
 * 供 AdminBeautify 插件概要页使用
 * 支持：折线图 (line)、极坐标柱状图 (polar)
 */
(function (g) {
    'use strict';

    /* ── 工具函数 ── */
    function svgEl(tag, attrs) {
        var el = document.createElementNS('http://www.w3.org/2000/svg', tag);
        if (attrs) {
            for (var k in attrs) {
                if (attrs[k] !== null && attrs[k] !== undefined) {
                    el.setAttribute(k, attrs[k]);
                }
            }
        }
        return el;
    }

    function domEl(tag, css) {
        var el = document.createElement(tag);
        if (css) el.style.cssText = css;
        return el;
    }

    var PALETTE = [
        '#6750a4', '#e9c46a', '#2a9d8f', '#e76f51',
        '#b5838d', '#457b9d', '#a8dadc', '#f4a261'
    ];

    function getTheme() {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            dark:    dark,
            text:    dark ? '#cac4d0' : '#49454f',
            grid:    dark ? 'rgba(202,196,208,0.1)' : 'rgba(73,69,79,0.08)',
            primary: dark ? '#d0bcff' : '#6750a4',
            bg:      dark ? '#2b2930' : '#ffffff',
            border:  dark ? '#49454f' : '#e7e0ec'
        };
    }

    /* ── 主题切换监听（MutationObserver）──
     * 每个图表注册后，主题切换时自动重绘 */
    var _themeObs = null;
    var _themeRegs = [];   // [{el, fn}, ...]
    function registerTheme(container, fn) {
        // 同一容器只保留最新的重绘函数
        for (var i = 0; i < _themeRegs.length; i++) {
            if (_themeRegs[i].el === container) {
                _themeRegs[i].fn = fn;
                return;
            }
        }
        _themeRegs.push({ el: container, fn: fn });
        if (!_themeObs) {
            _themeObs = new MutationObserver(function () {
                // 移除已离开 DOM 的容器，重绘剩余的
                _themeRegs = _themeRegs.filter(function (r) { return document.contains(r.el); });
                _themeRegs.forEach(function (r) { r.fn(); });
            });
            _themeObs.observe(document.documentElement, {
                attributes: true, attributeFilter: ['data-theme']
            });
        }
    }

    /* ── 精确坐标转换：client → SVG 内部坐标 ── */
    function clientToSVG(svg, clientX, clientY) {
        try {
            var pt = svg.createSVGPoint();
            pt.x = clientX;
            pt.y = clientY;
            return pt.matrixTransform(svg.getScreenCTM().inverse());
        } catch (ex) {
            // fallback: 简单线性映射
            var r = svg.getBoundingClientRect();
            var vb = svg.viewBox.baseVal;
            return { x: (clientX - r.left) / r.width * vb.width, y: (clientY - r.top) / r.height * vb.height };
        }
    }

    /* ── Tooltip 创建 ── */
    function makeTip(parent, t) {
        var tip = domEl('div',
            'position:absolute;display:none;pointer-events:none;z-index:20;' +
            'background:' + t.bg + ';border:1px solid ' + t.border + ';' +
            'border-radius:8px;padding:5px 10px;font-size:12px;line-height:1.6;' +
            'color:' + t.text + ';white-space:nowrap;' +
            'box-shadow:0 2px 12px rgba(0,0,0,.18);'
        );
        parent.appendChild(tip);
        return tip;
    }

    /* ── Tooltip 定位（防止溢出容器边界）── */
    function placeTip(tip, container, mouseX, mouseY) {
        tip.style.display = 'block';
        var tw = tip.offsetWidth;
        var th = tip.offsetHeight;
        var cw = container.offsetWidth;
        var ch = container.offsetHeight;
        var lx = mouseX + 14;
        var ty = mouseY - 36;
        if (lx + tw > cw - 4) lx = mouseX - tw - 14;
        if (lx < 4) lx = 4;
        if (ty < 4) ty = mouseY + 12;
        if (ty + th > ch - 4) ty = mouseY - th - 4;
        tip.style.left = lx + 'px';
        tip.style.top  = ty + 'px';
    }

    /* ═══════════════════════════════════════
     * 折线图
     * opts: { xData:string[], yData:number[], color?:string, colorDark?:string }
     * ═══════════════════════════════════════ */
    function renderLine(container, opts) {
        container.innerHTML = '';
        container.style.position = 'relative';

        var xData    = opts.xData    || [];
        var yData    = opts.yData    || [];
        var subtitle = opts.subtitle || '';
        var t        = getTheme();
        // 亮/暗各一色，主题切换时自动选择
        var color = t.dark
            ? (opts.colorDark || opts.color || t.primary)
            : (opts.color     || t.primary);

        /* 内部坐标系（固定 400×180，通过 viewBox 自动缩放） */
        var IW = 400, IH = 180;
        var P  = { t: 14, r: 14, b: subtitle ? 48 : 34, l: 36 };
        var pw = IW - P.l - P.r;
        var ph = IH - P.t - P.b;

        var svg = svgEl('svg', {
            viewBox: '0 0 ' + IW + ' ' + IH,
            width: '100%',
            height: '100%',
            style: 'overflow:visible;display:block;'
        });

        /* 空数据 */
        if (!xData.length || !yData.length) {
            var nt = svgEl('text', {
                x: IW / 2, y: IH / 2,
                'text-anchor': 'middle',
                fill: t.text, 'font-size': '13', opacity: '0.6'
            });
            nt.textContent = '暂无数据';
            svg.appendChild(nt);
            container.appendChild(svg);
            registerTheme(container, function () { renderLine(container, opts); });
            return;
        }

        var yMax = Math.max.apply(null, yData) || 1;

        function px(i) {
            return P.l + (xData.length <= 1 ? pw / 2 : i / (xData.length - 1) * pw);
        }
        function py(v) {
            return P.t + ph - (v / yMax) * ph;
        }

        /* 渐变定义 */
        var defs = svgEl('defs');
        var gid  = 'abcl-' + Math.random().toString(36).slice(2, 7);
        var grad = svgEl('linearGradient', { id: gid, x1: '0', y1: '0', x2: '0', y2: '1' });
        var s1   = svgEl('stop', { offset: '0%',   'stop-color': color, 'stop-opacity': '0.28' });
        var s2   = svgEl('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': '0' });
        grad.appendChild(s1);
        grad.appendChild(s2);
        defs.appendChild(grad);
        svg.appendChild(defs);

        /* 网格线 + Y 轴标签 */
        for (var gi = 0; gi <= 3; gi++) {
            var gv = Math.round(yMax * gi / 3);
            var gy = py(gv);
            svg.appendChild(svgEl('line', {
                x1: P.l, y1: gy, x2: P.l + pw, y2: gy,
                stroke: t.grid, 'stroke-width': '1'
            }));
            var gt = svgEl('text', {
                x: P.l - 5, y: gy + 4,
                'text-anchor': 'end',
                fill: t.text, 'font-size': '10'
            });
            gt.textContent = gv;
            svg.appendChild(gt);
        }

        /* 路径数据 */
        var pts = yData.map(function (v, i) { return [px(i), py(v)]; });

        var aD = 'M' + P.l + ',' + (P.t + ph) + ' L' + pts[0][0] + ',' + pts[0][1];
        var lD = 'M' + pts[0][0] + ',' + pts[0][1];

        for (var pi = 1; pi < pts.length; pi++) {
            var cpx1 = pts[pi - 1][0] + (pts[pi][0] - pts[pi - 1][0]) * 0.45;
            var cpx2 = pts[pi][0]     - (pts[pi][0] - pts[pi - 1][0]) * 0.45;
            var seg  = ' C' + cpx1 + ',' + pts[pi - 1][1] +
                       ' '  + cpx2 + ',' + pts[pi][1] +
                       ' '  + pts[pi][0] + ',' + pts[pi][1];
            aD += seg;
            lD += seg;
        }
        aD += ' L' + pts[pts.length - 1][0] + ',' + (P.t + ph) + ' Z';

        svg.appendChild(svgEl('path', { d: aD, fill: 'url(#' + gid + ')' }));
        svg.appendChild(svgEl('path', {
            d: lD, fill: 'none',
            stroke: color, 'stroke-width': '2.5',
            'stroke-linecap': 'round', 'stroke-linejoin': 'round'
        }));

        /* 数据点 */
        pts.forEach(function (p) {
            svg.appendChild(svgEl('circle', {
                cx: p[0], cy: p[1], r: '4',
                fill: t.bg, stroke: color, 'stroke-width': '2.5'
            }));
        });

        /* X 轴标签：≤7点逐一，≤10点每2个，否则最多8个 */
        var xStep = xData.length <= 7 ? 1 : (xData.length <= 10 ? 2 : Math.max(1, Math.ceil(xData.length / 8)));
        for (var xi = 0; xi < xData.length; xi += xStep) {
            var xt = svgEl('text', {
                x: px(xi), y: IH - P.b + 14,
                'text-anchor': 'middle',
                fill: t.text, 'font-size': '10'
            });
            xt.textContent = String(xData[xi]); // 调用方已预格式化标签
            svg.appendChild(xt);
        }

        /* 悬停竖线 */
        var hLine = svgEl('line', {
            x1: P.l, y1: P.t, x2: P.l, y2: P.t + ph,
            stroke: t.grid, 'stroke-width': '1', 'stroke-dasharray': '3 2',
            display: 'none'
        });
        svg.appendChild(hLine);

        container.appendChild(svg);

        /* Tooltip */
        var tip  = makeTip(container, t);
        var stepSVG = xData.length <= 1 ? pw : pw / (xData.length - 1);

        svg.addEventListener('mousemove', function (e) {
            var sp  = clientToSVG(svg, e.clientX, e.clientY);
            var mx  = sp.x - P.l;
            var idx = Math.max(0, Math.min(xData.length - 1, Math.round(mx / stepSVG)));
            var rect = container.getBoundingClientRect();
            tip.textContent = xData[idx] + ': ' + yData[idx] + ' 篇';
            placeTip(tip, container, e.clientX - rect.left, e.clientY - rect.top);
            hLine.setAttribute('x1', px(idx));
            hLine.setAttribute('x2', px(idx));
            hLine.setAttribute('display', '');
        });
        svg.addEventListener('mouseleave', function () {
            tip.style.display = 'none';
            hLine.setAttribute('display', 'none');
        });

        /* 副标题 */
        if (subtitle) {
            var st = svgEl('text', {
                x: IW / 2, y: IH - 4,
                'text-anchor': 'middle',
                fill: t.text, 'font-size': '10', opacity: '0.5'
            });
            st.textContent = subtitle;
            svg.appendChild(st);
        }

        /* 主题切换时重绘 */
        registerTheme(container, function () { renderLine(container, opts); });
    }

    /* ═══════════════════════════════════════
     * 柱状图
     * opts: { xData:string[], yData:number[], color?:string, colorDark?:string, subtitle?:string }
     * ═══════════════════════════════════════ */
    function renderBar(container, opts) {
        container.innerHTML = '';
        container.style.position = 'relative';

        var xData    = opts.xData    || [];
        var yData    = opts.yData    || [];
        var subtitle = opts.subtitle || '';
        var t        = getTheme();
        var color    = t.dark
            ? (opts.colorDark || opts.color || t.primary)
            : (opts.color     || t.primary);

        /* 内部坐标系：底部为 subtitle 留额外空间 */
        var IW = 400, IH = 180;
        var P  = { t: 14, r: 14, b: subtitle ? 48 : 34, l: 36 };
        var pw = IW - P.l - P.r;
        var ph = IH - P.t - P.b;

        var svg = svgEl('svg', {
            viewBox: '0 0 ' + IW + ' ' + IH,
            width: '100%', height: '100%',
            style: 'overflow:visible;display:block;'
        });

        /* 空数据 */
        if (!xData.length || !yData.length) {
            var nt = svgEl('text', {
                x: IW / 2, y: IH / 2, 'text-anchor': 'middle',
                fill: t.text, 'font-size': '13', opacity: '0.6'
            });
            nt.textContent = '暂无数据';
            svg.appendChild(nt);
            container.appendChild(svg);
            registerTheme(container, function () { renderBar(container, opts); });
            return;
        }

        var n    = xData.length;
        var yMax = Math.max.apply(null, yData) || 1;

        function bx(i) { return P.l + i / n * pw; }
        function py(v)  { return P.t + ph - (v / yMax) * ph; }

        /* 网格线 + Y 轴标签 */
        for (var gi = 0; gi <= 3; gi++) {
            var gv = Math.round(yMax * gi / 3);
            var gy = py(gv);
            svg.appendChild(svgEl('line', {
                x1: P.l, y1: gy, x2: P.l + pw, y2: gy,
                stroke: t.grid, 'stroke-width': '1'
            }));
            var gt = svgEl('text', {
                x: P.l - 5, y: gy + 4, 'text-anchor': 'end',
                fill: t.text, 'font-size': '10'
            });
            gt.textContent = gv;
            svg.appendChild(gt);
        }

        /* 底部基准线 */
        svg.appendChild(svgEl('line', {
            x1: P.l, y1: P.t + ph, x2: P.l + pw, y2: P.t + ph,
            stroke: t.grid, 'stroke-width': '1'
        }));

        /* 柱子 */
        var slotW = pw / n;
        var barW  = Math.min(slotW * 0.65, 24);
        var bars  = [];

        for (var bi = 0; bi < n; bi++) {
            var bLeft  = P.l + bi * slotW + (slotW - barW) / 2;
            var bH     = (yData[bi] / yMax) * ph;
            var bTop   = P.t + ph - bH;
            var barClr = yData[bi] > 0 ? color : t.grid;
            var r4     = Math.min(4, barW / 2);

            /* 圆角矩形（顶部两角圆角）*/
            var d = bH > 0
                ? 'M' + bLeft + ',' + (bTop + r4) +
                  ' Q' + bLeft + ',' + bTop + ' ' + (bLeft + r4) + ',' + bTop +
                  ' L' + (bLeft + barW - r4) + ',' + bTop +
                  ' Q' + (bLeft + barW) + ',' + bTop + ' ' + (bLeft + barW) + ',' + (bTop + r4) +
                  ' L' + (bLeft + barW) + ',' + (P.t + ph) +
                  ' L' + bLeft + ',' + (P.t + ph) + ' Z'
                : '';

            if (d) {
                svg.appendChild(svgEl('path', {
                    d: d, fill: barClr, opacity: yData[bi] > 0 ? '0.85' : '0.3'
                }));
            }
            bars.push({ x: bLeft, w: barW, top: bTop, h: bH, val: yData[bi], label: xData[bi] });
        }

        /* X 轴标签（自动步长，最多 8 个） */
        var xStep = Math.max(1, Math.ceil(n / 8));
        for (var xi = 0; xi < n; xi += xStep) {
            var xt = svgEl('text', {
                x: P.l + xi * slotW + slotW / 2,
                y: P.t + ph + 14,
                'text-anchor': 'middle',
                fill: t.text, 'font-size': '10'
            });
            xt.textContent = String(xData[xi]);
            svg.appendChild(xt);
        }

        /* 副标题（底部居中） */
        if (subtitle) {
            var st = svgEl('text', {
                x: IW / 2, y: IH - 4,
                'text-anchor': 'middle',
                fill: t.text, 'font-size': '10', opacity: '0.5'
            });
            st.textContent = subtitle;
            svg.appendChild(st);
        }

        container.appendChild(svg);

        /* Tooltip：透明 hit 区 + 悬停高亮 */
        var tip = makeTip(container, t);
        var highlighted = -1;

        /* 悬停高亮用一个共享 path 覆盖 */
        var hlPath = svgEl('path', { fill: t.text, opacity: '0', style: 'pointer-events:none;' });
        svg.appendChild(hlPath);

        /* 透明 hit 矩形（列宽区域，提高命中率） */
        for (var hi = 0; hi < n; hi++) {
            (function (idx) {
                var hitRect = svgEl('rect', {
                    x: P.l + idx * slotW, y: P.t,
                    width: slotW, height: ph,
                    fill: 'transparent'
                });
                hitRect.addEventListener('mouseenter', function (e) {
                    if (highlighted !== idx) {
                        highlighted = idx;
                        /* 高亮当前柱 */
                        var b = bars[idx];
                        if (b.h > 0) {
                            var r4h = Math.min(4, b.w / 2);
                            hlPath.setAttribute('d',
                                'M' + b.x + ',' + (b.top + r4h) +
                                ' Q' + b.x + ',' + b.top + ' ' + (b.x + r4h) + ',' + b.top +
                                ' L' + (b.x + b.w - r4h) + ',' + b.top +
                                ' Q' + (b.x + b.w) + ',' + b.top + ' ' + (b.x + b.w) + ',' + (b.top + r4h) +
                                ' L' + (b.x + b.w) + ',' + (P.t + ph) +
                                ' L' + b.x + ',' + (P.t + ph) + ' Z'
                            );
                            hlPath.setAttribute('opacity', '0.12');
                        } else {
                            hlPath.setAttribute('opacity', '0');
                        }
                    }
                    var rect = container.getBoundingClientRect();
                    var b2 = bars[idx];
                    tip.textContent = b2.label + '：' + b2.val + ' 篇';
                    placeTip(tip, container, e.clientX - rect.left, e.clientY - rect.top);
                });
                hitRect.addEventListener('mouseleave', function () {
                    tip.style.display = 'none';
                    hlPath.setAttribute('opacity', '0');
                    highlighted = -1;
                });
                svg.appendChild(hitRect);
            })(hi);
        }

        /* 主题切换时重绘 */
        registerTheme(container, function () { renderBar(container, opts); });
    }

    /* ═══════════════════════════════════════
     * 极坐标柱状图（圆环分段）
     * opts: { data:[{name,value,color?},...] }
     * ═══════════════════════════════════════ */
    function renderPolar(container, opts) {
        container.innerHTML = '';
        container.style.position = 'relative';

        var data = opts.data || [];
        var t    = getTheme();

        /* 内部坐标系（固定 300×200） */
        var IW = 300, IH = 200;
        var LX = 162;          /* 图例起始 X */
        var CX = 76, CY = 100; /* 圆心 */
        var R_OUT = 62, R_IN = 30;

        var svg = svgEl('svg', {
            viewBox: '0 0 ' + IW + ' ' + IH,
            width: '100%',
            height: '100%',
            style: 'overflow:visible;display:block;'
        });

        /* 空数据 */
        if (!data.length) {
            var nt = svgEl('text', {
                x: IW / 2, y: IH / 2,
                'text-anchor': 'middle',
                fill: t.text, 'font-size': '13', opacity: '0.6'
            });
            nt.textContent = '暂无数据';
            svg.appendChild(nt);
            container.appendChild(svg);
            registerTheme(container, function () { renderPolar(container, opts); });
            return;
        }

        var total = data.reduce(function (s, d) { return s + d.value; }, 0) || 1;
        var N     = data.length;

        function toXY(r, a) {
            return [
                CX + r * Math.cos(a - Math.PI / 2),
                CY + r * Math.sin(a - Math.PI / 2)
            ];
        }

        function arcPath(r1, r2, a1, a2) {
            var p1 = toXY(r2, a1), p2 = toXY(r2, a2);
            var p3 = toXY(r1, a2), p4 = toXY(r1, a1);
            var lg = (a2 - a1 > Math.PI) ? 1 : 0;
            return 'M' + p1[0] + ' ' + p1[1] +
                   ' A' + r2 + ' ' + r2 + ' 0 ' + lg + ' 1 ' + p2[0] + ' ' + p2[1] +
                   ' L' + p3[0] + ' ' + p3[1] +
                   ' A' + r1 + ' ' + r1 + ' 0 ' + lg + ' 0 ' + p4[0] + ' ' + p4[1] + ' Z';
        }

        /* 按比例计算各段角度（真实饼图） */
        var GAP      = N > 1 ? 0.04 : 0;
        var segs     = [];
        var runAngle = 0;

        for (var i = 0; i < N; i++) {
            var spanAngle = (data[i].value / total) * Math.PI * 2;
            var a1  = runAngle + GAP / 2;
            var a2  = runAngle + spanAngle - GAP / 2;
            var clr = data[i].color || PALETTE[i % PALETTE.length];
            if (a2 > a1) {
                var path = svgEl('path', {
                    d: arcPath(R_IN, R_OUT, a1, a2),
                    fill: clr,
                    style: 'cursor:default;'
                });
                svg.appendChild(path);
            }
            segs.push({ a1: runAngle, a2: runAngle + spanAngle, d: data[i], color: clr });
            runAngle += spanAngle;
        }

        /* 中心只显示总数（纯数字） */
        var ctv = svgEl('text', {
            x: CX, y: CY + 6,
            'text-anchor': 'middle',
            fill: t.text, 'font-size': '20', 'font-weight': '700'
        });
        ctv.textContent = total;
        svg.appendChild(ctv);

        /* 图例（SVG 内右侧） */
        var lyStart = Math.max(6, IH / 2 - N * 20 / 2);
        for (var li = 0; li < N; li++) {
            var lColor = data[li].color || PALETTE[li % PALETTE.length];
            var lPct   = Math.round(data[li].value / total * 100);
            var ly     = lyStart + li * 20;

            svg.appendChild(svgEl('rect', {
                x: LX, y: ly, width: '8', height: '8',
                rx: '4', fill: lColor
            }));

            var lName = svgEl('text', {
                x: LX + 12, y: ly + 7,
                fill: t.text, 'font-size': '11'
            });
            lName.textContent = data[li].name.length > 7
                ? data[li].name.slice(0, 7) + '…'
                : data[li].name;
            svg.appendChild(lName);

            var lVal = svgEl('text', {
                x: IW - 4, y: ly + 7,
                'text-anchor': 'end',
                fill: t.text, 'font-size': '10', opacity: '0.65'
            });
            lVal.textContent = lPct + '%';
            svg.appendChild(lVal);
        }

        container.appendChild(svg);

        /* Tooltip */
        var tip = makeTip(container, t);

        svg.addEventListener('mousemove', function (e) {
            var sp   = clientToSVG(svg, e.clientX, e.clientY);
            var mx   = sp.x - CX;
            var my   = sp.y - CY;
            var dist = Math.sqrt(mx * mx + my * my);
            var rect = container.getBoundingClientRect();

            if (dist < R_IN || dist > R_OUT) {
                tip.style.display = 'none';
                return;
            }

            /* 计算鼠标角度（0 = 12点，顺时针） */
            var angle = Math.atan2(my, mx) + Math.PI / 2;
            if (angle < 0) angle += Math.PI * 2;
            if (angle >= Math.PI * 2) angle -= Math.PI * 2;

            for (var si = 0; si < segs.length; si++) {
                if (angle >= segs[si].a1 && angle < segs[si].a2) {
                    var pct2 = Math.round(segs[si].d.value / total * 100);
                    tip.textContent = segs[si].d.name + '：' + segs[si].d.value + ' 条（' + pct2 + '%）';
                    placeTip(tip, container, e.clientX - rect.left, e.clientY - rect.top);
                    return;
                }
            }
            tip.style.display = 'none';
        });
        svg.addEventListener('mouseleave', function () {
            tip.style.display = 'none';
        });

        /* 主题切换时重绘 */
        registerTheme(container, function () { renderPolar(container, opts); });
    }

    /* ── 公开 API ── */
    g.ABCharts = {
        line:  function (container, opts) { renderLine(container, opts); },
        bar:   function (container, opts) { renderBar(container, opts); },
        polar: function (container, opts) { renderPolar(container, opts); }
    };

})(window);
