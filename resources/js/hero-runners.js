/*
 * The home hero: the Saturday crowd running through Ibadan at sunrise.
 *
 * Everything is drawn from code onto two canvases, one behind the wordmark and one in front of it, so people can
 * pass behind "Ibadan." and in front of it. There are no image files and no animation library.
 *
 * A person is a small skeleton, about 100 units tall, standing on the ground at y = 0 with y pointing up and facing
 * +x. Feet are placed first and the knees are worked out from them, so a foot on the ground stays exactly where it
 * landed and the same body can shift smoothly from a walk to a sprint.
 */

var TAU = Math.PI * 2;
var DEG = Math.PI / 180;

var INK = "#1C1917";
var ORANGE = "#FFB000";
var GREEN = "#3DAE6E";
var SKY = "#62AEEA";
var PINK = "#F08DB8";
var CORAL = "#FF7A66";
var CREAM = "#FFF1CC";
var WHITE = "#FFFFFF";
var HAIR = "#15100D";
var SKIN = ["#3B2318", "#4C2D1D", "#5C3924", "#6D452C", "#7F5436"];

var AIR = "#EEECE7";
var ROAD = "#DAD6CB";
var ROAD_PAINT = "#E7E4DC";
var ROAD_DASH = "#F4F2EC";
var KERB = "#C7C2B4";
var SHADOW = "#BDB8AA";

var THIGH = 27;
var SHIN = 27;
var LEG = THIGH + SHIN;
var ANKLE = 5.6;
var HEEL = 3.8;
var BALL = 8.5;
var TORSO = 27.5;
var NECK = 4.2;
var HEAD = 6.9;
var UPPER_ARM = 17;
var FOREARM = 15.5;

/* The paces on the slider further down the page, so the readout here agrees with it */
var PACE_BANDS = [[270, "Flying"], [360, "Tempo"], [450, "Easy run"], [540, "Chatty jog"], [Infinity, "Brisk walk"]];
var SLOWEST_PACE = 780;
var FASTEST_PACE = 210;

/*
 * The people. pace nudges them above or below the crowd's tempo; walkers keep walking however fast everyone else goes.
 */
var CAST = [
    { hair: "braids", skin: 1, top: "singlet", topColor: ORANGE, bottom: "tights", bottomColor: INK, shoe: PINK, accent: PINK, bib: true, girth: .94, height: 1, pace: .1 },
    { hair: "low", beard: true, skin: 2, top: "tee", topColor: INK, stripe: ORANGE, bottom: "shorts", bottomColor: ORANGE, shoe: SKY, girth: 1.03, height: 1.04, pace: .03 },
    { hair: "hijab", hairColor: SKY, skin: 3, top: "long", topColor: CREAM, bottom: "tights", bottomColor: INK, shoe: CORAL, girth: .98, height: .95, pace: -.05 },
    { hair: "low", band: WHITE, skin: 0, top: "tee", topColor: CORAL, bottom: "shorts", bottomColor: INK, shoe: ORANGE, girth: 1.3, belly: 2.6, height: 1, pace: -.1 },
    { hair: "puff", skin: 3, top: "tee", topColor: WHITE, stripe: GREEN, bottom: "shorts", bottomColor: GREEN, shoe: INK, accent: GREEN, flag: ORANGE, girth: 1, height: .97, pace: 0 },
    { hair: "afro", skin: 2, top: "singlet", topColor: GREEN, bottom: "shorts", bottomColor: INK, shoe: ORANGE, girth: 1, height: 1.02, pace: .07 },
    { hair: "pony", skin: 4, top: "tee", topColor: PINK, bottom: "shorts", bottomColor: INK, shoe: SKY, accent: WHITE, girth: .96, height: .96, pace: .02 },
    { hair: "bald", shades: true, skin: 1, top: "singlet", topColor: INK, bottom: "shorts", bottomColor: SKY, shoe: ORANGE, girth: 1.02, height: 1.05, pace: .14, bib: true },
    { hair: "tie", hairColor: ORANGE, skin: 2, top: "tee", topColor: INK, bottom: "tights", bottomColor: SKY, shoe: CORAL, girth: 1.18, belly: 1.4, height: .94, pace: -.12 },
    { hair: "cap", hairColor: INK, skin: 3, top: "tee", topColor: ORANGE, bottom: "shorts", bottomColor: INK, shoe: SKY, girth: .95, height: 1.06, pace: .05 },
    { hair: "twists", visor: CORAL, skin: 1, top: "tee", topColor: SKY, bottom: "shorts", bottomColor: CORAL, shoe: INK, accent: CORAL, girth: .95, height: .98, pace: 0 },
    { hair: "cap", hairColor: GREEN, grey: true, skin: 2, top: "tee", topColor: CREAM, stripe: INK, bottom: "shorts", bottomColor: GREEN, shoe: ORANGE, girth: 1.12, belly: 1.8, height: .98, pace: -.5, maxPace: .1 },
    { hair: "tie", hairColor: PINK, skin: 4, top: "long", topColor: GREEN, bottom: "tights", bottomColor: INK, shoe: PINK, girth: 1.22, belly: 1.6, height: .94, pace: -.5, maxPace: .12 },
    { hair: "braids", skin: 0, top: "tee", topColor: CORAL, bottom: "tights", bottomColor: INK, shoe: ORANGE, accent: ORANGE, girth: 1, height: 1, pace: .08 },
];

/* ---------- Small maths and colour helpers ---------- */

function clamp(value, low, high) { return value < low ? low : value > high ? high : value; }
function lerp(from, to, amount) { return from + (to - from) * amount; }
function smooth(from, to, value) {
    var t = clamp((value - from) / (to - from), 0, 1);
    return t * t * (3 - 2 * t);
}
function wrap(value, size) { return ((value % size) + size) % size; }
function seededRandom(seed) {
    return function () {
        seed = (seed * 16807) % 2147483647;
        return (seed - 1) / 2147483646;
    };
}
function channels(hex) {
    var n = parseInt(hex.slice(1), 16);
    return [n >> 16, (n >> 8) & 255, n & 255];
}
function mix(hexA, hexB, amount) {
    var a = channels(hexA), b = channels(hexB);
    var out = (Math.round(lerp(a[0], b[0], amount)) << 16) | (Math.round(lerp(a[1], b[1], amount)) << 8) | Math.round(lerp(a[2], b[2], amount));
    return "#" + (out + 16777216).toString(16).slice(1);
}
/* The limbs on the far side of the body: a shade darker, or lighter when the colour is already almost black */
function farSide(hex) {
    var c = channels(hex);
    return c[0] * .3 + c[1] * .59 + c[2] * .11 < 62 ? mix(hex, WHITE, .16) : mix(hex, INK, .2);
}

/* ---------- Movement ---------- */

/*
 * How the body moves at a given effort: 0 is a stroll, about .5 a comfortable jog, 1 a flat-out sprint.
 * stride is how far the body travels in one full cycle (two steps), and stance is the share of that cycle a foot is
 * on the ground. A foot on the ground moves back at exactly the body's speed, which is what stops it sliding.
 */
function readGait(gait, effort) {
    var running = smooth(.12, .42, effort);
    gait.effort = effort;
    gait.stride = lerp(88, 280, effort);
    gait.cycle = lerp(1.14, .66, effort);
    gait.stance = effort < .42 ? lerp(.62, .4, effort / .42) : lerp(.4, .26, (effort - .42) / .58);
    gait.travel = gait.stride * gait.stance;
    gait.landX = gait.travel * .38;
    gait.landPitch = lerp(18, 3, effort) * DEG;
    gait.offPitch = lerp(38, 64, effort) * DEG;
    gait.hipHeight = ANKLE + LEG * lerp(.97, .91, effort);
    gait.bob = lerp(1.5, -3.8, running) * (running > 0 ? lerp(.7, 1, effort) : 1);
    gait.lean = lerp(2.5, 15, effort) * DEG;
    gait.armBase = lerp(-2, 7, effort) * DEG;
    gait.armSwing = lerp(16, 50, effort) * DEG;
    gait.elbow = lerp(14, 84, running) * DEG;
    gait.elbowSwing = lerp(6, 24, effort) * DEG;
    gait.rotation = lerp(1, 3.2, effort);
}
function makeLeg() {
    return { hipX: 0, hipY: 0, kneeX: 0, kneeY: 0, ankleX: 0, ankleY: 0, pitch: 0, planted: false, swing: 0 };
}
function makeArm() {
    return { shoulderX: 0, shoulderY: 0, elbowX: 0, elbowY: 0, handX: 0, handY: 0 };
}

/* Where the ankle sits for a foot on the ground, rolling over the heel as it lands and the ball of the foot as it leaves */
function ankleOnGround(out, flatX, pitch) {
    var cos = Math.cos(pitch), sin = Math.sin(pitch), fromX, pivotX;
    if (pitch > 0) {
        fromX = HEEL;
        pivotX = flatX - HEEL;
    } else {
        fromX = -BALL;
        pivotX = flatX + BALL;
    }
    out.ankleX = pivotX + fromX * cos - ANKLE * sin;
    out.ankleY = fromX * sin + ANKLE * cos;
    out.pitch = pitch;
}

var offFoot = makeLeg();
var landFoot = makeLeg();
var swingX = new Float32Array(8);
var swingY = new Float32Array(8);

/* Catmull-Rom through the swing's waypoints: off the ground, up behind, through under the hips, reach and land */
function swingPoint(t, values) {
    var at = t * 5, index = Math.min(Math.floor(at), 4), f = at - index;
    var a = values[index], b = values[index + 1], c = values[index + 2], d = values[index + 3];
    return .5 * (2 * b + (c - a) * f + (2 * a - 5 * b + 4 * c - d) * f * f + (3 * b - a - 3 * c + d) * f * f * f);
}
function placeFoot(leg, phase, gait) {
    if (phase < gait.stance) {
        var s = phase / gait.stance;
        var pitch = s < .2
            ? gait.landPitch * (1 - smooth(0, .2, s))
            : s > .5 ? -gait.offPitch * smooth(.5, 1, s) * smooth(.5, 1, s) : 0;
        ankleOnGround(leg, gait.landX - gait.travel * s, pitch);
        leg.planted = true;
        leg.swing = 0;
        return;
    }
    var t = (phase - gait.stance) / (1 - gait.stance);
    var effort = gait.effort;
    ankleOnGround(offFoot, gait.landX - gait.travel, -gait.offPitch);
    ankleOnGround(landFoot, gait.landX, gait.landPitch);
    swingX[0] = offFoot.ankleX + 7;
    swingY[0] = offFoot.ankleY - 5;
    swingX[1] = offFoot.ankleX;
    swingY[1] = offFoot.ankleY;
    swingX[2] = offFoot.ankleX - lerp(1, 5, effort);
    swingY[2] = lerp(12, 24, effort);
    swingX[3] = lerp(-10, -30, effort);
    swingY[3] = lerp(14, 40, effort);
    swingX[4] = lerp(7, 8, effort);
    swingY[4] = lerp(11, 34, effort);
    swingX[5] = lerp(19, 34, effort);
    swingY[5] = lerp(9, 24, effort);
    swingX[6] = landFoot.ankleX;
    swingY[6] = landFoot.ankleY;
    swingX[7] = landFoot.ankleX - 6;
    swingY[7] = landFoot.ankleY - 1;
    leg.ankleX = swingPoint(t, swingX);
    leg.ankleY = swingPoint(t, swingY);
    leg.planted = false;
    leg.swing = t;
}
/* The knee, from the hip and ankle. It always bends forwards. A foot in the air also points along with its shin */
function placeKnee(leg, gait) {
    var dx = leg.ankleX - leg.hipX, dy = leg.ankleY - leg.hipY;
    var distance = Math.sqrt(dx * dx + dy * dy) || .001;
    var reach = Math.min(distance, LEG - .05);
    var along = (THIGH * THIGH - SHIN * SHIN + reach * reach) / (2 * reach);
    var out = Math.sqrt(Math.max(0, THIGH * THIGH - along * along));
    var ux = dx / distance, uy = dy / distance;
    leg.kneeX = leg.hipX + ux * along - uy * out;
    leg.kneeY = leg.hipY + uy * along + ux * out;
    leg.ankleX = leg.hipX + ux * reach;
    leg.ankleY = leg.hipY + uy * reach;
    if (!leg.planted) {
        var t = leg.swing;
        var shin = Math.atan2(leg.ankleY - leg.kneeY, leg.ankleX - leg.kneeX);
        var natural = shin + Math.PI / 2 - lerp(38, 4, t) * DEG;
        leg.pitch = lerp(lerp(-gait.offPitch, natural, smooth(0, .22, t)), gait.landPitch, smooth(.8, 1, t));
    }
}
function placeArm(arm, phase, gait, shoulderX, shoulderY, lean, raised) {
    var swing = -Math.cos(TAU * (phase + .04));
    var angle = raised ? 40 * DEG + swing * 4 * DEG : gait.armBase + gait.armSwing * swing + lean * .6;
    var bend = raised ? 96 * DEG : gait.elbow + gait.elbowSwing * swing;
    arm.shoulderX = shoulderX;
    arm.shoulderY = shoulderY;
    arm.elbowX = shoulderX + UPPER_ARM * Math.sin(angle);
    arm.elbowY = shoulderY - UPPER_ARM * Math.cos(angle);
    arm.handX = arm.elbowX + FOREARM * Math.sin(angle + bend);
    arm.handY = arm.elbowY - FOREARM * Math.cos(angle + bend);
}
/* A foot on the ground limits how high the hips can be, because the leg can only stretch so far */
function highestHip(leg) {
    if (!leg.planted) {
        return Infinity;
    }
    var dx = leg.ankleX - leg.hipX;
    return leg.ankleY + Math.sqrt(Math.max(0, (LEG - .1) * (LEG - .1) - dx * dx));
}
function makePerson(spec) {
    return {
        spec: spec,
        phase: 0,
        pose: { gait: {}, near: makeLeg(), far: makeLeg(), armNear: makeArm(), armFar: makeArm(), hipX: 0, hipY: 0, lean: 0, spine: TORSO, chestX: 0, chestY: 0, headX: 0, headY: 0, tilt: 0, step: 0 },
    };
}
function posePerson(person, phase, effort) {
    var pose = person.pose, gait = pose.gait;
    var raisedArm = Boolean(person.spec.flag);
    readGait(gait, effort);
    placeFoot(pose.near, wrap(phase, 1), gait);
    placeFoot(pose.far, wrap(phase + .5, 1), gait);

    var turn = gait.rotation * Math.cos(TAU * (phase + .06));
    pose.near.hipX = turn * .55;
    pose.far.hipX = -turn * .55;
    var hipY = gait.hipHeight + gait.bob * Math.cos(TAU * 2 * (phase - gait.stance * .45));
    hipY = Math.min(hipY, highestHip(pose.near), highestHip(pose.far));
    pose.hipX = 0;
    pose.hipY = hipY;
    pose.near.hipY = hipY;
    pose.far.hipY = hipY;
    placeKnee(pose.near, gait);
    placeKnee(pose.far, gait);

    var lean = gait.lean + Math.sin(TAU * 2 * phase) * 1.2 * DEG * effort;
    var spine = TORSO;
    var sin = Math.sin(lean), cos = Math.cos(lean);
    pose.lean = lean;
    pose.spine = spine;
    pose.chestX = sin * spine;
    pose.chestY = hipY + cos * spine;
    var twist = -turn * .85;
    placeArm(pose.armNear, wrap(phase, 1), gait, pose.chestX - sin * 3 + cos * twist, pose.chestY - cos * 3 - sin * twist, lean, raisedArm);
    placeArm(pose.armFar, wrap(phase + .5, 1), gait, pose.chestX - sin * 3 - cos * twist, pose.chestY - cos * 3 + sin * twist, lean, false);

    var neckLean = lean * .7;
    pose.headX = pose.chestX + Math.sin(neckLean) * (NECK + HEAD) + 1.4;
    pose.headY = pose.chestY + Math.cos(neckLean) * (NECK + HEAD);
    pose.tilt = lean * .3 + Math.sin(TAU * 2 * phase) * .02;
    pose.step = phase * 2;
}

/* ---------- Drawing shapes ---------- */

/* A limb: round at both ends and tapering between them. Every shape winds the same way so overlaps stay solid */
function limb(ctx, ax, ay, ra, bx, by, rb) {
    var dx = bx - ax, dy = by - ay;
    var length = Math.sqrt(dx * dx + dy * dy) || .001;
    var angle = Math.atan2(dy, dx);
    var spread = Math.acos(clamp((ra - rb) / length, -1, 1));
    ctx.moveTo(ax + ra * Math.cos(angle + spread), ay + ra * Math.sin(angle + spread));
    ctx.arc(ax, ay, ra, angle + spread, angle - spread + TAU);
    ctx.arc(bx, by, rb, angle - spread, angle + spread);
    ctx.closePath();
}
/* The same, cut square at the far end: shorts and sleeves */
function cuff(ctx, ax, ay, ra, bx, by, rb) {
    var dx = bx - ax, dy = by - ay;
    var length = Math.sqrt(dx * dx + dy * dy) || .001;
    var nx = -dy / length, ny = dx / length;
    var angle = Math.atan2(dy, dx);
    ctx.moveTo(bx + nx * rb, by + ny * rb);
    ctx.lineTo(ax + nx * ra, ay + ny * ra);
    ctx.arc(ax, ay, ra, angle + Math.PI / 2, angle + Math.PI * 1.5);
    ctx.lineTo(bx - nx * rb, by - ny * rb);
    ctx.closePath();
}
function disc(ctx, x, y, r) {
    ctx.moveTo(x + r, y);
    ctx.arc(x, y, r, 0, TAU);
}

/* Thigh with a little muscle at the front, calf with a little at the back */
function traceThigh(ctx, leg, girth, grow) {
    var dx = leg.kneeX - leg.hipX, dy = leg.kneeY - leg.hipY;
    var mx = leg.hipX + dx * .42 - dy * .03, my = leg.hipY + dy * .42 + dx * .03;
    limb(ctx, leg.hipX, leg.hipY, 6.5 * girth + grow, mx, my, 5.6 * girth + grow);
    limb(ctx, mx, my, 5.6 * girth + grow, leg.kneeX, leg.kneeY, 3.5 + (girth - 1) * 2 + grow);
}
function traceCalf(ctx, leg, girth, grow) {
    var dx = leg.ankleX - leg.kneeX, dy = leg.ankleY - leg.kneeY;
    var mx = leg.kneeX + dx * .3 + dy * .05, my = leg.kneeY + dy * .3 - dx * .05;
    limb(ctx, leg.kneeX, leg.kneeY, 3.5 + (girth - 1) * 2 + grow, mx, my, 3.9 * girth + grow);
    limb(ctx, mx, my, 3.9 * girth + grow, leg.ankleX, leg.ankleY, 2 + grow);
}
function traceFootSimple(ctx, leg) {
    var cos = Math.cos(leg.pitch), sin = Math.sin(leg.pitch);
    limb(ctx, leg.ankleX - HEEL * .7 * cos + (ANKLE * .5) * sin, leg.ankleY - HEEL * .7 * sin - (ANKLE * .5) * cos, 2.8,
        leg.ankleX + 9.4 * cos + (ANKLE * .68) * sin, leg.ankleY + 9.4 * sin - (ANKLE * .68) * cos, 1.9);
}
/* The body's outline, from a list of [how far up the spine, how far forward] pairs, smoothed into curves */
var outlineX = new Float32Array(10);
var outlineY = new Float32Array(10);
var outlineAlong = [.03, .4, .74, .99, 1.03, .8, .42, .03];
var outlineForward = new Float32Array(10);
function traceTorso(ctx, pose, spec, grow) {
    var girth = spec.girth, belly = spec.belly || 0;
    var ux = Math.sin(pose.lean), uy = Math.cos(pose.lean);
    var nx = uy, ny = -ux;
    var spine = pose.spine;
    var count = 8;
    outlineForward[0] = 6.6 * girth + belly * .4;
    outlineForward[1] = 5.9 * girth + belly;
    outlineForward[2] = 7.1 * girth;
    outlineForward[3] = 3.6 * lerp(1, girth, .5);
    outlineForward[4] = -3.8 * lerp(1, girth, .5);
    outlineForward[5] = -7 * girth;
    outlineForward[6] = -6 * girth;
    outlineForward[7] = -7.4 * girth;
    for (var point = 0; point < count; point++) {
        var forward = outlineForward[point] + (outlineForward[point] < 0 ? -grow : grow);
        outlineX[point] = pose.hipX + ux * spine * outlineAlong[point] + nx * forward;
        outlineY[point] = pose.hipY + uy * spine * outlineAlong[point] + ny * forward;
    }
    ctx.moveTo((outlineX[count - 1] + outlineX[0]) / 2, (outlineY[count - 1] + outlineY[0]) / 2);
    for (var i = 0; i < count; i++) {
        var next = (i + 1) % count;
        ctx.quadraticCurveTo(outlineX[i], outlineY[i], (outlineX[i] + outlineX[next]) / 2, (outlineY[i] + outlineY[next]) / 2);
    }
    ctx.closePath();
}

/* A strand that trails behind and ripples with the stride: braids, ponytails, headband ends, the flag's edges */
var strandX = new Float32Array(10);
var strandY = new Float32Array(10);
function layStrand(startX, startY, segments, length, angle, ripple, wave) {
    strandX[0] = startX;
    strandY[0] = startY;
    var step = length / segments;
    for (var i = 1; i <= segments; i++) {
        var a = angle + ripple * Math.sin(wave - i * .75) * (i / segments);
        strandX[i] = strandX[i - 1] + Math.cos(a) * step;
        strandY[i] = strandY[i - 1] + Math.sin(a) * step;
    }
}
function strokeStrand(ctx, segments) {
    ctx.beginPath();
    ctx.moveTo(strandX[0], strandY[0]);
    for (var i = 1; i < segments; i++) {
        ctx.quadraticCurveTo(strandX[i], strandY[i], (strandX[i] + strandX[i + 1]) / 2, (strandY[i] + strandY[i + 1]) / 2);
    }
    ctx.lineTo(strandX[segments], strandY[segments]);
    ctx.stroke();
}

/* ---------- Dressing and drawing a person ---------- */

function dress(spec, haze) {
    function tone(hex) { return haze ? mix(hex, AIR, haze) : hex; }
    var skin = SKIN[spec.skin];
    return {
        skin: tone(skin),
        skinFar: tone(mix(skin, INK, .32)),
        skinDeep: tone(mix(skin, INK, .24)),
        top: tone(spec.topColor),
        topFar: tone(farSide(spec.topColor)),
        stripe: spec.stripe ? tone(spec.stripe) : null,
        bottom: tone(spec.bottomColor),
        bottomFar: tone(farSide(spec.bottomColor)),
        shoe: tone(spec.shoe),
        shoeFar: tone(farSide(spec.shoe)),
        sole: tone(spec.shoe === WHITE ? INK : WHITE),
        sock: tone(WHITE),
        sockFar: tone(mix(WHITE, INK, .16)),
        hair: tone(spec.grey ? "#8D877E" : HAIR),
        wrap: spec.hairColor ? tone(spec.hairColor) : null,
        wrapFar: spec.hairColor ? tone(farSide(spec.hairColor)) : null,
        accent: tone(spec.accent || ORANGE),
        band: spec.band ? tone(spec.band) : null,
        visor: spec.visor ? tone(spec.visor) : null,
        flag: spec.flag ? tone(spec.flag) : null,
        ink: tone(INK),
        paper: tone(WHITE),
    };
}
function fillPath(ctx, color) {
    ctx.fillStyle = color;
    ctx.fill();
}
function drawShoe(ctx, leg, upper, sole) {
    ctx.save();
    ctx.translate(leg.ankleX, leg.ankleY);
    ctx.rotate(leg.pitch);
    ctx.beginPath();
    ctx.moveTo(-3.3, 1.9);
    ctx.lineTo(2.7, 1.9);
    ctx.quadraticCurveTo(3.6, -1, 6.6, -1.7);
    ctx.quadraticCurveTo(11.2, -2.3, 11.9, -4.3);
    ctx.lineTo(-4.3, -4.3);
    ctx.quadraticCurveTo(-4.6, -.9, -3.3, 1.9);
    ctx.closePath();
    fillPath(ctx, upper);
    ctx.beginPath();
    ctx.moveTo(-4.5, -4.1);
    ctx.lineTo(11.9, -4.1);
    ctx.quadraticCurveTo(12.5, -4.9, 11.6, -5.6);
    ctx.lineTo(-3.4, -5.6);
    ctx.quadraticCurveTo(-4.7, -5.5, -4.5, -4.1);
    ctx.closePath();
    fillPath(ctx, sole);
    ctx.restore();
}
function drawLeg(ctx, leg, person, kit, far) {
    var spec = person.spec, girth = spec.girth;
    var bottom = far ? kit.bottomFar : kit.bottom;
    ctx.beginPath();
    if (spec.bottom === "tights") {
        traceThigh(ctx, leg, girth, .25);
        traceCalf(ctx, leg, girth, .25);
        fillPath(ctx, bottom);
    } else {
        traceThigh(ctx, leg, girth, 0);
        traceCalf(ctx, leg, girth, 0);
        fillPath(ctx, far ? kit.skinFar : kit.skin);
        ctx.beginPath();
        cuff(ctx, leg.hipX, leg.hipY, 7.3 * girth, leg.hipX + (leg.kneeX - leg.hipX) * .58, leg.hipY + (leg.kneeY - leg.hipY) * .58, 5.8 * girth + .3);
        fillPath(ctx, bottom);
        ctx.beginPath();
        var dx = leg.kneeX - leg.ankleX, dy = leg.kneeY - leg.ankleY, size = Math.sqrt(dx * dx + dy * dy) || 1;
        limb(ctx, leg.ankleX, leg.ankleY, 2.3, leg.ankleX + dx / size * 4, leg.ankleY + dy / size * 4, 2.5);
        fillPath(ctx, far ? kit.sockFar : kit.sock);
    }
    drawShoe(ctx, leg, far ? kit.shoeFar : kit.shoe, kit.sole);
}
function drawArm(ctx, arm, person, kit, far) {
    var spec = person.spec, girth = spec.girth;
    var skin = far ? kit.skinFar : kit.skin;
    var top = far ? kit.topFar : kit.top;
    var size = lerp(1, girth, .7);
    if (spec.top === "long") {
        ctx.beginPath();
        limb(ctx, arm.shoulderX, arm.shoulderY, 3.9 * size, arm.elbowX, arm.elbowY, 3 * size);
        limb(ctx, arm.elbowX, arm.elbowY, 3 * size, arm.handX, arm.handY, 2.3);
        fillPath(ctx, top);
        ctx.beginPath();
        disc(ctx, arm.handX, arm.handY, 2.6);
        fillPath(ctx, skin);
        return;
    }
    ctx.beginPath();
    limb(ctx, arm.shoulderX, arm.shoulderY, 3.5 * size, arm.elbowX, arm.elbowY, 2.6 * size);
    limb(ctx, arm.elbowX, arm.elbowY, 2.6 * size, arm.handX, arm.handY, 1.9);
    disc(ctx, arm.handX, arm.handY, 2.8);
    fillPath(ctx, skin);
    if (spec.top === "tee") {
        ctx.beginPath();
        cuff(ctx, arm.shoulderX, arm.shoulderY, 4.5 * size, arm.shoulderX + (arm.elbowX - arm.shoulderX) * .52, arm.shoulderY + (arm.elbowY - arm.shoulderY) * .52, 3.7 * size);
        fillPath(ctx, top);
    }
}
/* A flat patch on the chest, placed by how far up the spine and how far forward each edge sits */
function tracePatch(ctx, pose, lowAlong, lowFront, highAlong, highFront, highBack, lowBack) {
    var ux = Math.sin(pose.lean), uy = Math.cos(pose.lean), spine = pose.spine;
    ctx.beginPath();
    ctx.moveTo(pose.hipX + ux * spine * lowAlong + uy * lowFront, pose.hipY + uy * spine * lowAlong - ux * lowFront);
    ctx.lineTo(pose.hipX + ux * spine * highAlong + uy * highFront, pose.hipY + uy * spine * highAlong - ux * highFront);
    ctx.lineTo(pose.hipX + ux * spine * highAlong + uy * highBack, pose.hipY + uy * spine * highAlong - ux * highBack);
    ctx.lineTo(pose.hipX + ux * spine * lowAlong + uy * lowBack, pose.hipY + uy * spine * lowAlong - ux * lowBack);
    ctx.closePath();
}
function drawTorso(ctx, pose, person, kit, detail) {
    var spec = person.spec, girth = spec.girth;
    ctx.beginPath();
    traceTorso(ctx, pose, spec, 0);
    fillPath(ctx, kit.top);
    if (kit.stripe) {
        tracePatch(ctx, pose, .46, 6.1 * girth + (spec.belly || 0) * .7, .62, 6.7 * girth, -6.3 * girth, -6.1 * girth);
        fillPath(ctx, kit.stripe);
    }
    if (spec.bib && detail > 1) {
        tracePatch(ctx, pose, .34, 5.4 * girth, .72, 6.4 * girth, -1.4, -1.4);
        fillPath(ctx, kit.paper);
        tracePatch(ctx, pose, .4, 3.8, .47, 3.8, .2, .2);
        fillPath(ctx, kit.ink);
        tracePatch(ctx, pose, .56, 4.2, .63, 4.2, .2, .2);
        fillPath(ctx, kit.ink);
    }
}
function drawHairBehind(ctx, person, kit, pose) {
    var spec = person.spec, effort = pose.gait.effort;
    var trail = lerp(-112, -158, effort) * DEG;
    var wave = pose.step * TAU;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    if (spec.hair === "afro") {
        ctx.beginPath();
        disc(ctx, -1.2, 2.2, 10.8);
        for (var i = 0; i < 9; i++) {
            var a = (i / 9) * TAU + .3;
            disc(ctx, -1.2 + Math.cos(a) * 9.4, 2.2 + Math.sin(a) * 9.4, 3);
        }
        fillPath(ctx, kit.hair);
    } else if (spec.hair === "braids") {
        ctx.strokeStyle = kit.hair;
        ctx.lineWidth = 2.1;
        for (var b = 0; b < 3; b++) {
            layStrand(-6.2, 2.4 - b * 1.6, 6, 25 - b * 2.6, trail + b * .1, .42, wave - b * .5);
            strokeStrand(ctx, 6);
            ctx.beginPath();
            disc(ctx, strandX[6], strandY[6], 1.5);
            fillPath(ctx, kit.accent);
        }
    } else if (spec.hair === "twists") {
        ctx.strokeStyle = kit.hair;
        ctx.lineWidth = 2.5;
        for (var w = 0; w < 5; w++) {
            layStrand(-6 + w * 1.1, 6.2 - w * .7, 3, 10, trail + .85 - w * .34, .4, wave - w * .7);
            strokeStrand(ctx, 3);
        }
    } else if (spec.hair === "pony") {
        ctx.strokeStyle = kit.hair;
        ctx.lineWidth = 4.2;
        layStrand(-6.8, 4.4, 5, 15, trail + .1, .55, wave);
        strokeStrand(ctx, 5);
        ctx.lineWidth = 2.6;
        layStrand(strandX[3], strandY[3], 2, 8, trail + .1, .6, wave - 1.4);
        strokeStrand(ctx, 2);
    } else if (spec.hair === "puff") {
        ctx.beginPath();
        disc(ctx, -4.6, 8.7, 5.9);
        disc(ctx, -8, 6.6, 2.8);
        disc(ctx, -1.2, 11.2, 2.8);
        fillPath(ctx, kit.hair);
    } else if (spec.hair === "hijab") {
        ctx.fillStyle = kit.wrapFar;
        layStrand(-7.5, -7, 4, 15, trail + .1, .4, wave);
        ctx.strokeStyle = kit.wrapFar;
        ctx.lineWidth = 6;
        strokeStrand(ctx, 4);
    }
}
function drawHairCap(ctx, color) {
    ctx.beginPath();
    ctx.moveTo(5.7, 3.3);
    ctx.quadraticCurveTo(5.3, 7.7, -.6, 8.2);
    ctx.quadraticCurveTo(-7.8, 8.3, -7.8, .9);
    ctx.quadraticCurveTo(-7.8, -2.6, -5.8, -4.6);
    ctx.quadraticCurveTo(-3.6, -3.7, -2.6, -1.5);
    ctx.quadraticCurveTo(-2.5, 1.9, -.6, 2.1);
    ctx.quadraticCurveTo(2.9, 2.2, 5.7, 3.3);
    ctx.closePath();
    fillPath(ctx, color);
}
function drawFace(ctx, kit, spec, detail) {
    ctx.beginPath();
    ctx.ellipse(.5, -.5, 6.8, 7.3, 0, 0, TAU);
    fillPath(ctx, kit.skin);
    ctx.beginPath();
    ctx.moveTo(6.7, 1.2);
    ctx.quadraticCurveTo(9, -1.2, 6.9, -2.1);
    ctx.closePath();
    fillPath(ctx, kit.skin);
    if (detail < 2) {
        return;
    }
    ctx.beginPath();
    ctx.ellipse(-.9, -.4, 1.35, 1.9, -.15, 0, TAU);
    fillPath(ctx, kit.skinDeep);
    if (!spec.shades) {
        ctx.beginPath();
        disc(ctx, 4.7, 1.1, .62);
        fillPath(ctx, kit.ink);
    }
    ctx.beginPath();
    ctx.moveTo(3.5, -3.7);
    ctx.quadraticCurveTo(5, -4.7, 6.3, -3.6);
    ctx.lineWidth = .62;
    ctx.lineCap = "round";
    ctx.strokeStyle = kit.ink;
    ctx.stroke();
}
function drawHead(ctx, pose, person, kit, detail) {
    var spec = person.spec;
    var wave = pose.step * TAU;
    var trail = lerp(-125, -168, pose.gait.effort) * DEG;

    ctx.beginPath();
    limb(ctx, pose.chestX - .2, pose.chestY - 1.5, 3.6, pose.headX - 1.4, pose.headY - 3.5, 3);
    fillPath(ctx, kit.skinDeep);

    ctx.save();
    ctx.translate(pose.headX, pose.headY);
    ctx.rotate(-pose.tilt);

    drawHairBehind(ctx, person, kit, pose);
    drawFace(ctx, kit, spec, detail);

    if (spec.hair === "low" || spec.hair === "afro" || spec.hair === "braids" || spec.hair === "pony" || spec.hair === "puff" || spec.hair === "twists") {
        drawHairCap(ctx, kit.hair);
    }
    if (spec.beard) {
        ctx.beginPath();
        ctx.moveTo(-.8, -1.8);
        ctx.quadraticCurveTo(-.2, -7.6, 3.6, -8.5);
        ctx.quadraticCurveTo(7.3, -8.2, 7.1, -4.8);
        ctx.quadraticCurveTo(5, -2.9, 2.6, -3.3);
        ctx.quadraticCurveTo(.9, -2.7, -.8, -1.8);
        ctx.closePath();
        fillPath(ctx, kit.hair);
    }
    if (spec.band) {
        ctx.beginPath();
        ctx.moveTo(-7.9, 4.6);
        ctx.quadraticCurveTo(0, 7.1, 6.6, 4.6);
        ctx.lineTo(6.9, 2.6);
        ctx.quadraticCurveTo(0, 4.6, -8, 1.9);
        ctx.closePath();
        fillPath(ctx, kit.band);
        ctx.lineCap = "round";
        ctx.strokeStyle = kit.band;
        ctx.lineWidth = 1.5;
        layStrand(-7.6, 3.2, 3, 10, trail, .6, wave);
        strokeStrand(ctx, 3);
        layStrand(-7.6, 3.2, 3, 8, trail - .34, .6, wave - .8);
        strokeStrand(ctx, 3);
    }
    if (spec.hair === "cap") {
        ctx.beginPath();
        ctx.moveTo(-5.8, -3.4);
        ctx.quadraticCurveTo(-8.2, 0, -7.2, 2.2);
        ctx.lineTo(-4, 2);
        ctx.quadraticCurveTo(-4, -2, -5.8, -3.4);
        ctx.closePath();
        fillPath(ctx, kit.hair);
        ctx.beginPath();
        ctx.moveTo(-7.5, 2);
        ctx.quadraticCurveTo(-7.8, 8.4, -.4, 8.7);
        ctx.quadraticCurveTo(6.6, 8.2, 6.8, 3.1);
        ctx.closePath();
        fillPath(ctx, kit.wrap);
        ctx.beginPath();
        ctx.moveTo(4.2, 3.9);
        ctx.quadraticCurveTo(10, 4.2, 13, 2.2);
        ctx.quadraticCurveTo(9, 1.5, 4.2, 2.4);
        ctx.closePath();
        fillPath(ctx, kit.wrapFar);
    }
    if (spec.visor) {
        ctx.beginPath();
        ctx.moveTo(-7.7, 3.4);
        ctx.quadraticCurveTo(0, 6.1, 6.5, 4.6);
        ctx.lineTo(6.9, 2.8);
        ctx.quadraticCurveTo(0, 3.9, -7.9, 1.2);
        ctx.closePath();
        fillPath(ctx, kit.visor);
        ctx.beginPath();
        ctx.moveTo(5, 4.8);
        ctx.quadraticCurveTo(10, 4.4, 12.6, 2.4);
        ctx.quadraticCurveTo(9, 2, 5, 3);
        ctx.closePath();
        fillPath(ctx, kit.visor);
    }
    if (spec.hair === "tie") {
        ctx.beginPath();
        ctx.moveTo(6.5, 3.3);
        ctx.quadraticCurveTo(6.8, 9.6, -.6, 10.6);
        ctx.quadraticCurveTo(-9, 10.4, -8.8, 2.6);
        ctx.quadraticCurveTo(-8.4, -.8, -6.2, -3);
        ctx.quadraticCurveTo(-1, 2.6, 6.5, 3.3);
        ctx.closePath();
        fillPath(ctx, kit.wrap);
        ctx.lineCap = "round";
        ctx.strokeStyle = kit.wrapFar;
        ctx.lineWidth = 2.1;
        layStrand(-7.8, 6.6, 3, 8, trail + .7, .5, wave);
        strokeStrand(ctx, 3);
        layStrand(-7.8, 6.6, 3, 7, trail + 1.4, .5, wave - .8);
        strokeStrand(ctx, 3);
        ctx.beginPath();
        disc(ctx, -7.6, 6.8, 2.1);
        fillPath(ctx, kit.wrapFar);
    }
    if (spec.hair === "hijab") {
        ctx.beginPath();
        ctx.moveTo(6.2, 3.8);
        ctx.quadraticCurveTo(6.2, 9.7, -.5, 9.8);
        ctx.quadraticCurveTo(-9.6, 9.4, -9.7, .4);
        ctx.quadraticCurveTo(-10, -7, -9.8, -13);
        ctx.quadraticCurveTo(-1, -15.8, 7.5, -12.2);
        ctx.quadraticCurveTo(7.4, -8.6, 6.7, -6.6);
        ctx.quadraticCurveTo(2.2, -5.6, 1.7, -1);
        ctx.quadraticCurveTo(1.7, 2.8, 6.2, 3.8);
        ctx.closePath();
        fillPath(ctx, kit.wrap);
    }
    if (spec.shades && detail > 1) {
        ctx.beginPath();
        ctx.moveTo(-.6, 1.8);
        ctx.lineTo(3, 2.1);
        ctx.lineTo(7.2, 2.1);
        ctx.quadraticCurveTo(7.3, .1, 5.4, .1);
        ctx.quadraticCurveTo(3.3, .1, 3, 1.4);
        ctx.lineTo(-.6, 1.3);
        ctx.closePath();
        fillPath(ctx, kit.ink);
    }
    ctx.restore();
}
function drawFlag(ctx, pose, kit) {
    var hand = pose.armNear;
    var wave = pose.step * TAU * .8;
    var topX = hand.handX + 1.5, topY = hand.handY + 37;
    ctx.beginPath();
    ctx.moveTo(hand.handX - .8, hand.handY - 7);
    ctx.lineTo(topX, topY);
    ctx.lineCap = "round";
    ctx.lineWidth = 1.1;
    ctx.strokeStyle = kit.ink;
    ctx.stroke();
    var angle = 180 * DEG + pose.lean;
    layStrand(topX, topY, 5, 25, angle, .34, wave);
    ctx.beginPath();
    ctx.moveTo(strandX[0], strandY[0]);
    for (var i = 1; i < 6; i++) { ctx.lineTo(strandX[i], strandY[i]); }
    layStrand(topX - 1, topY - 13, 5, 23, angle, .34, wave - .35);
    for (var j = 5; j >= 0; j--) { ctx.lineTo(strandX[j], strandY[j]); }
    ctx.closePath();
    fillPath(ctx, kit.flag);
}
function drawPerson(ctx, person, kit, detail) {
    var pose = person.pose, spec = person.spec;
    drawArm(ctx, pose.armFar, person, kit, true);
    drawLeg(ctx, pose.far, person, kit, true);
    ctx.beginPath();
    disc(ctx, pose.hipX, pose.hipY, 7.6 * spec.girth);
    fillPath(ctx, kit.bottom);
    drawLeg(ctx, pose.near, person, kit, false);
    drawTorso(ctx, pose, person, kit, detail);
    drawHead(ctx, pose, person, kit, detail);
    if (spec.flag) {
        drawFlag(ctx, pose, kit);
    }
    drawArm(ctx, pose.armNear, person, kit, false);
}
function traceSilhouetteLeg(ctx, leg, girth) {
    traceThigh(ctx, leg, girth, .1);
    traceCalf(ctx, leg, girth, .1);
    traceFootSimple(ctx, leg);
}
function traceSilhouetteArm(ctx, arm, size) {
    limb(ctx, arm.shoulderX, arm.shoulderY, 3.6 * size, arm.elbowX, arm.elbowY, 2.6 * size);
    limb(ctx, arm.elbowX, arm.elbowY, 2.6 * size, arm.handX, arm.handY, 2.3);
}
/* One flat shape for the whole body: a person's shadow, and the distant crowd */
function traceSilhouette(ctx, person) {
    var pose = person.pose, spec = person.spec, girth = spec.girth;
    var size = lerp(1, girth, .7);
    ctx.beginPath();
    traceSilhouetteLeg(ctx, pose.far, girth);
    traceSilhouetteLeg(ctx, pose.near, girth);
    traceSilhouetteArm(ctx, pose.armFar, size);
    traceSilhouetteArm(ctx, pose.armNear, size);
    disc(ctx, pose.hipX, pose.hipY, 7.4 * girth);
    traceTorso(ctx, pose, spec, 0);
    limb(ctx, pose.chestX, pose.chestY - 1.5, 3.6, pose.headX - 1.4, pose.headY - 3.5, 3);
    disc(ctx, pose.headX + .4, pose.headY - .3, 7.3);
    if (spec.hair === "afro") {
        disc(ctx, pose.headX - 1.2, pose.headY + 2.2, 11.6);
    } else if (spec.hair === "puff") {
        disc(ctx, pose.headX - 4.6, pose.headY + 8.7, 6);
    } else if (spec.hair === "tie" || spec.hair === "cap") {
        disc(ctx, pose.headX - .6, pose.headY + 1.4, 8.6);
    }
}

/* ---------- The city, painted once and then slid past at different speeds ---------- */

function makeLayer(width, height, pixelRatio, paint) {
    var canvas = document.createElement("canvas");
    canvas.width = Math.ceil(width * pixelRatio);
    canvas.height = Math.ceil(height * pixelRatio);
    var ctx = canvas.getContext("2d");
    ctx.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
    paint(ctx, width, height);
    return { canvas: canvas, width: width, height: height };
}
function ridge(ctx, width, height, color, base, heightShare, waves) {
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.moveTo(0, height);
    for (var x = 0; x <= width; x += 12) {
        var rise = 0;
        for (var i = 0; i < waves.length; i++) {
            rise += waves[i][1] * Math.sin(TAU * waves[i][0] * x / width + waves[i][2]);
        }
        ctx.lineTo(x, height - height * heightShare * (base + rise));
    }
    ctx.lineTo(width, height);
    ctx.closePath();
    ctx.fill();
}
/* The seven hills. Whole numbers of waves, so the two ends of the picture meet */
function paintHills(ctx, width, height) {
    ridge(ctx, width, height, "#E5E1D8", .62, .62, [[2, .18, .4], [5, .1, 1.7], [9, .03, .3]]);
    ridge(ctx, width, height, "#DEDAD0", .42, .62, [[3, .16, 2.1], [7, .07, .6]]);
}
function bumpHill(ctx, x, ground, reach, rise, color) {
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.moveTo(x - reach, ground);
    ctx.bezierCurveTo(x - reach * .45, ground, x - reach * .4, ground - rise, x, ground - rise);
    ctx.bezierCurveTo(x + reach * .4, ground - rise, x + reach * .45, ground, x + reach, ground);
    ctx.closePath();
    ctx.fill();
}
/* Cocoa House: the tall slab with its ribbons of windows */
function paintCocoaHouse(ctx, x, ground, u, body, light) {
    var width = 76 * u, height = 322 * u;
    ctx.fillStyle = body;
    ctx.fillRect(x - 18 * u, ground - 36 * u, width + 36 * u, 36 * u);
    ctx.fillRect(x, ground - height, width, height);
    ctx.fillRect(x + 16 * u, ground - height - 16 * u, width - 32 * u, 16 * u);
    ctx.fillRect(x + width / 2 - u, ground - height - 50 * u, 2 * u, 36 * u);
    ctx.fillStyle = light;
    for (var y = ground - height + 14 * u; y < ground - 44 * u; y += 11.5 * u) {
        ctx.fillRect(x + 7 * u, y, width - 14 * u, 5 * u);
    }
}
/* Bower's Tower: the slim lookout on its hill */
function paintBowerTower(ctx, x, ground, u, body, light, hill) {
    var hillRise = 88 * u;
    bumpHill(ctx, x, ground, 190 * u, hillRise, hill);
    var base = ground - hillRise + 3 * u;
    ctx.fillStyle = body;
    ctx.fillRect(x - 8 * u, base - 118 * u, 16 * u, 120 * u);
    ctx.fillRect(x - 14 * u, base - 130 * u, 28 * u, 12 * u);
    ctx.beginPath();
    ctx.moveTo(x - 11 * u, base - 130 * u);
    ctx.lineTo(x, base - 150 * u);
    ctx.lineTo(x + 11 * u, base - 130 * u);
    ctx.closePath();
    ctx.fill();
    ctx.fillStyle = light;
    for (var y = base - 108 * u; y < base - 8 * u; y += 22 * u) {
        ctx.fillRect(x - 1.5 * u, y, 3 * u, 11 * u);
    }
    ctx.fillRect(x - 9 * u, base - 126 * u, 18 * u, 4 * u);
}
/* Mapo Hall: columns and a pediment, up on Mapo Hill */
function paintMapoHall(ctx, x, ground, u, body, light, hill) {
    var hillRise = 66 * u;
    var width = 196 * u;
    bumpHill(ctx, x, ground, 240 * u, hillRise, hill);
    var base = ground - hillRise + 4 * u;
    ctx.fillStyle = body;
    ctx.fillRect(x - width / 2 - 8 * u, base - 8 * u, width + 16 * u, 8 * u);
    ctx.fillRect(x - width / 2, base - 72 * u, width, 64 * u);
    ctx.fillRect(x - width / 2 - 5 * u, base - 80 * u, width + 10 * u, 8 * u);
    ctx.beginPath();
    ctx.moveTo(x - width / 2 - 8 * u, base - 80 * u);
    ctx.lineTo(x, base - 112 * u);
    ctx.lineTo(x + width / 2 + 8 * u, base - 80 * u);
    ctx.closePath();
    ctx.fill();
    ctx.fillStyle = light;
    var gaps = 9;
    var gap = 12 * u;
    var spacing = (width - gap) / gaps;
    for (var i = 0; i < gaps; i++) {
        ctx.fillRect(x - width / 2 + gap + spacing * i, base - 66 * u, gap, 54 * u);
    }
}
/* A mosque, with its dome and minaret */
function paintMosque(ctx, x, ground, u, body, light) {
    ctx.fillStyle = body;
    ctx.fillRect(x, ground - 62 * u, 112 * u, 62 * u);
    ctx.beginPath();
    ctx.arc(x + 56 * u, ground - 62 * u, 30 * u, Math.PI, 0);
    ctx.fill();
    ctx.fillRect(x + 56 * u - u, ground - 106 * u, 2 * u, 16 * u);
    ctx.fillRect(x + 122 * u, ground - 150 * u, 14 * u, 150 * u);
    ctx.fillRect(x + 118 * u, ground - 122 * u, 22 * u, 6 * u);
    ctx.beginPath();
    ctx.arc(x + 129 * u, ground - 150 * u, 7 * u, Math.PI, 0);
    ctx.fill();
    ctx.fillStyle = light;
    for (var i = 0; i < 5; i++) {
        ctx.fillRect(x + (10 + i * 20) * u, ground - 46 * u, 11 * u, 26 * u);
    }
}
function paintCity(ctx, width, height, u) {
    var random = seededRandom(4021);
    var body = "#D7D2C6", light = "#E4E0D7", hill = "#DAD5CA", landmark = "#CFC9BC", landmarkLight = "#DDD8CD";
    var ground = height;
    var x = 6 * u;
    while (x < width - 60 * u) {
        var w = (38 + random() * 62) * u;
        var h = (54 + random() * random() * 150) * u;
        ctx.fillStyle = body;
        ctx.fillRect(x, ground - h, w, h);
        ctx.fillStyle = light;
        var rows = Math.floor(h / (17 * u));
        var cols = Math.max(1, Math.floor(w / (15 * u)));
        for (var r = 0; r < rows; r++) {
            for (var c = 0; c < cols; c++) {
                if (random() > .32) {
                    ctx.fillRect(x + (5 + c * 15) * u, ground - h + (7 + r * 17) * u, 7 * u, 8 * u);
                }
            }
        }
        if (random() > .55) {
            ctx.fillStyle = body;
            ctx.fillRect(x + w * .3, ground - h - 11 * u, 16 * u, 11 * u);
            ctx.fillRect(x + w * .3 + 3 * u, ground - h - 15 * u, 2 * u, 5 * u);
        }
        x += w + (random() * 26 + 2) * u;
    }
    paintCocoaHouse(ctx, width * .2 - 38 * u, ground, u, landmark, landmarkLight);
    paintBowerTower(ctx, width * .5, ground, u, landmark, landmarkLight, hill);
    paintMosque(ctx, width * .66 - 70 * u, ground, u, landmark, landmarkLight);
    paintMapoHall(ctx, width * .82, ground, u, landmark, landmarkLight, hill);
}
function paintPalm(ctx, x, ground, u, random) {
    var height = (66 + random() * 38) * u, lean = (random() - .5) * 18 * u;
    ctx.strokeStyle = "#8A7A62";
    ctx.lineWidth = 3.2 * u;
    ctx.lineCap = "round";
    ctx.beginPath();
    ctx.moveTo(x, ground);
    ctx.quadraticCurveTo(x + lean * .2, ground - height * .6, x + lean, ground - height);
    ctx.stroke();
    ctx.fillStyle = "#6E9D7E";
    for (var i = 0; i < 7; i++) {
        var a = -Math.PI * (.04 + i * .153);
        var length = (24 + random() * 8) * u;
        var tipX = x + lean + Math.cos(a) * length, tipY = ground - height + Math.sin(a) * length * .75 + length * .28;
        ctx.beginPath();
        ctx.moveTo(x + lean, ground - height);
        ctx.quadraticCurveTo(x + lean + Math.cos(a) * length * .6, ground - height + Math.sin(a) * length - 4 * u, tipX, tipY);
        ctx.quadraticCurveTo(x + lean + Math.cos(a) * length * .4, ground - height + Math.sin(a) * length * .4, x + lean, ground - height);
        ctx.closePath();
        ctx.fill();
    }
}
function paintTree(ctx, x, ground, u, random) {
    var size = (22 + random() * 14) * u;
    ctx.fillStyle = "#8A7A62";
    ctx.fillRect(x - 2.2 * u, ground - size * 1.4, 4.4 * u, size * 1.4);
    ctx.fillStyle = random() > .5 ? "#7DA88A" : "#6E9D7E";
    ctx.beginPath();
    disc(ctx, x, ground - size * 2, size);
    disc(ctx, x - size * .75, ground - size * 1.55, size * .68);
    disc(ctx, x + size * .78, ground - size * 1.5, size * .72);
    ctx.fill();
}
/* The houses: the rust-brown iron roofs Ibadan is known for, row behind row */
function paintHouse(ctx, x, ground, width, wallHeight, roofHeight, random, u) {
    var walls = ["#EADFC9", "#E3D5BB", "#F0E7D6", "#DCCDB2"];
    var roofs = [["#B4632E", "#9A5126"], ["#A25527", "#8A4721"], ["#C07539", "#A4602C"], ["#94502A", "#7E4323"], ["#AE5C2C", "#924C24"]];
    var roof = roofs[Math.floor(random() * roofs.length)];
    var top = ground - wallHeight;
    ctx.fillStyle = walls[Math.floor(random() * walls.length)];
    ctx.fillRect(x, top, width, wallHeight);
    ctx.fillStyle = "#74634D";
    var windows = Math.max(1, Math.floor(width / (32 * u)));
    for (var i = 0; i < windows; i++) {
        ctx.fillRect(x + (i + .5) * width / windows - 5 * u, top + 8 * u, 10 * u, 12 * u);
    }
    var overhang = 6 * u, inset = width * .16;
    ctx.fillStyle = roof[0];
    ctx.beginPath();
    ctx.moveTo(x - overhang, top + 2 * u);
    ctx.lineTo(x + inset, top - roofHeight);
    ctx.lineTo(x + width - inset, top - roofHeight);
    ctx.lineTo(x + width + overhang, top + 2 * u);
    ctx.closePath();
    ctx.fill();
    ctx.fillStyle = roof[1];
    ctx.beginPath();
    ctx.moveTo(x + width - inset, top - roofHeight);
    ctx.lineTo(x + width + overhang, top + 2 * u);
    ctx.lineTo(x + width - inset * .7, top + 2 * u);
    ctx.closePath();
    ctx.fill();
    ctx.strokeStyle = roof[1];
    ctx.lineWidth = Math.max(.6, .9 * u);
    ctx.beginPath();
    for (var line = x + 2 * u; line < x + width - inset * .8; line += 7 * u) {
        var share = (line - x) / width;
        ctx.moveTo(line, top + 2 * u);
        ctx.lineTo(x + inset + share * (width - 2 * inset), top - roofHeight);
    }
    ctx.stroke();
}
function paintRoofs(ctx, width, height, u) {
    var random = seededRandom(9127);
    var ground = height;
    [height - 44 * u, height - 22 * u, height].forEach(function (rowGround, rowIndex) {
        var x = -2 * u + rowIndex * 14 * u;
        while (x < width - 70 * u) {
            var houseWidth = (58 + random() * 66) * u;
            if (x + houseWidth > width - 10 * u) {
                break;
            }
            if (random() > .86) {
                if (random() > .45) {
                    paintPalm(ctx, x + 12 * u, rowGround, u, random);
                } else {
                    paintTree(ctx, x + 14 * u, rowGround, u, random);
                }
                x += 26 * u;
            }
            paintHouse(ctx, x, rowGround, houseWidth, (19 + random() * 13) * u, (15 + random() * 11) * u, random, u);
            x += houseWidth + (random() * 12 + 6) * u;
        }
    });
    /* The low wall along the road */
    ctx.fillStyle = "#D2CCBF";
    ctx.fillRect(0, ground - 11 * u, width, 11 * u);
    ctx.fillStyle = "#C4BEB0";
    for (var x = 0; x < width; x += 38 * u) {
        ctx.fillRect(x, ground - 11 * u, 1.4 * u, 11 * u);
    }
    ctx.fillRect(0, ground - 11 * u, width, 1.4 * u);
}
/* The club's three lines, painted on the road and running past under everyone's feet */
var ROAD_WORDS = ["EVERYONE", "EVERY PACE", "ONE COMMUNITY"];
function makeRoadWords(height, pixelRatio) {
    var size = height * 1.5;
    var font = 'italic 900 ' + size + 'px "Anybody", "Arial Black", sans-serif';
    var measure = document.createElement("canvas").getContext("2d");
    var gap = size * 1.1;
    var total = 0;
    measure.font = font;
    var starts = ROAD_WORDS.map(function (word) {
        var start = total;
        total += measure.measureText(word).width + gap;
        return start;
    });
    return makeLayer(total, height, pixelRatio, function (ctx) {
        ctx.fillStyle = ROAD_PAINT;
        ctx.font = font;
        ROAD_WORDS.forEach(function (word, index) {
            ctx.save();
            ctx.translate(starts[index] + gap * .5, height * .94);
            ctx.transform(1, 0, -.42, .58, 0, 0);
            ctx.fillText(word, 0, 0);
            ctx.restore();
        });
    });
}

/* ---------- The scene ---------- */

var LANES = [
    { scale: .4, at: .025, haze: 0, front: false, detail: 0, spread: 1, shadow: false, roster: [1, 6, 11, 9, 2, 5, 12, 0, 8, 10, 7, 3], counts: [3, 7, 11] },
    { scale: .62, at: .15, haze: .28, front: false, detail: 1, spread: 1, shadow: true, roster: [11, 5, 8, 12, 13, 10, 1, 7], counts: [3, 5, 8] },
    { scale: .82, at: .42, haze: .05, front: true, detail: 2, spread: .75, shadow: true, roster: [4, 6, 2, 10, 5, 13], counts: [2, 4, 6] },
    { scale: 1, at: .72, haze: 0, front: true, detail: 2, spread: .6, shadow: true, roster: [0, 3, 9, 7], counts: [2, 3, 4] },
];
var FAR_CROWD = "#B7B1A4";

function paceText(tempo) {
    var seconds = Math.round(lerp(SLOWEST_PACE, FASTEST_PACE, tempo) / 15) * 15;
    var minutes = Math.floor(seconds / 60), rest = seconds % 60;
    return { time: minutes + ":" + (rest < 10 ? "0" : "") + rest, seconds: seconds };
}
function paceBand(seconds) {
    for (var i = 0; i < PACE_BANDS.length; i++) {
        if (seconds <= PACE_BANDS[i][0]) {
            return PACE_BANDS[i][1];
        }
    }
    return "";
}

export function startHeroRunners(hero, reduceMotion) {
    var backCanvas = hero.querySelector("[data-hero-back]");
    var frontCanvas = hero.querySelector("[data-hero-front]");
    var road = hero.querySelector("[data-hero-road]");
    var sun = hero.querySelector("[data-hero-sun]");
    var block = hero.querySelector(".wm-hl");
    var hud = hero.querySelector("[data-hero-hud]");
    var paceOut = hero.querySelector("[data-hero-pace]");
    var bandOut = hero.querySelector("[data-hero-band]");
    var pauseButton = hero.querySelector("[data-hero-pause]");
    if (!backCanvas || !frontCanvas || !road) {
        return;
    }
    var back = backCanvas.getContext("2d");
    var front = frontCanvas.getContext("2d");

    var quality = 0;
    var width = 0, height = 0, pixelRatio = 1;
    var horizon = 0, roadHeight = 0, unit = 0, sunX = 0, blockLeft = 0, blockRight = 0, blockHeight = 0;
    var hills, city, roofs, roadWords;
    var people = [], dust = [];
    var cameraGait = {};
    var camera = 0, clock = 0, tempo = reduceMotion ? .56 : .92, surge = 0;
    var pointerTempo = null, pointerAt = -99;
    var running = false, paused = false, onScreen = true, frameRequest = 0, lastFrame = 0;
    var slowFrames = 0, sampledFrames = 0, shownPace = "", shownCrowd = "";
    var laidOutWidth = 0, laidOutHeight = 0;
    var lastScrollY = window.scrollY;

    function crowdShape() {
        return (width < 620 ? 0 : width < 1100 ? 1 : 2) + ":" + quality + ":" + Math.round(Math.max(1, width / 1500) * 2);
    }
    function buildCrowd(isOpening) {
        var size = width < 620 ? 0 : width < 1100 ? 1 : 2;
        var widen = Math.max(1, width / 1500);
        people = [];
        LANES.forEach(function (lane) {
            var count = Math.round(lane.counts[size] * widen);
            if (quality > 1 && !lane.front) {
                count = lane.scale < .5 ? 0 : Math.ceil(count / 2);
            }
            for (var i = 0; i < count; i++) {
                var spec = CAST[lane.roster[i % lane.roster.length]];
                var person = makePerson(spec);
                person.lane = lane;
                person.kit = dress(spec, lane.haze);
                person.x = (i + .5 + (Math.random() - .5) * .55) / count * lane.loop;
                person.phase = Math.random();
                person.effort = reduceMotion ? clamp(tempo + spec.pace, .02, spec.maxPace || 1) : .9;
                person.enter = isOpening && !reduceMotion ? 1 + Math.random() * .9 : 0;
                people.push(person);
            }
        });
    }
    function buildScenery() {
        var skyline = Math.min(horizon, unit * 2);
        var u = skyline / 372;
        var tile = Math.max(width * 1.15, 900);
        var ratio = Math.min(pixelRatio, 2);
        hills = makeLayer(tile, skyline * .58, ratio, paintHills);
        city = makeLayer(tile * 1.2, skyline, ratio, function (ctx, w, h) { paintCity(ctx, w, h, u); });
        roofs = makeLayer(tile * 1.05, skyline * .34, ratio, function (ctx, w, h) { paintRoofs(ctx, w, h, Math.max(u, .62) * .92); });
        buildRoadWords();
    }
    function buildRoadWords() {
        roadWords = makeRoadWords(roadHeight * .27, Math.min(pixelRatio, 2));
    }
    function layout() {
        var box = hero.getBoundingClientRect();
        var roadBox = road.getBoundingClientRect();
        var horizonFromTop = roadBox.top - box.top;
        width = box.width;
        unit = clamp(width * .145, 142, 226);
        roadHeight = roadBox.height;
        var sceneTop = Math.max(0, horizonFromTop - unit * 2.06);
        height = (roadBox.bottom - box.top) - sceneTop;
        hero.style.setProperty("--road-end", (roadBox.bottom - box.top) + "px");
        horizon = horizonFromTop - sceneTop;
        var cap = quality === 0 ? 2 : quality === 1 ? 1.5 : 1;
        pixelRatio = Math.min(window.devicePixelRatio || 1, cap);
        [backCanvas, frontCanvas].forEach(function (canvas) {
            canvas.style.top = sceneTop + "px";
            canvas.style.height = height + "px";
            canvas.width = Math.round(width * pixelRatio);
            canvas.height = Math.round(height * pixelRatio);
        });
        sunX = sun.offsetLeft + sun.offsetWidth / 2;
        var blockBox = block.getBoundingClientRect();
        blockLeft = blockBox.left - box.left;
        blockRight = blockBox.right - box.left;
        blockHeight = blockBox.height;
        people.forEach(function (person) {
            person.share = person.x / person.lane.loop;
        });
        LANES.forEach(function (lane) {
            lane.pixels = unit / 100 * lane.scale;
            lane.y = horizon + roadHeight * lane.at;
            lane.margin = unit * lane.scale * 1.3;
            lane.loop = width + lane.margin * 2;
        });
        var shape = crowdShape();
        if (shape !== shownCrowd) {
            buildCrowd(shownCrowd === "");
            shownCrowd = shape;
        } else {
            people.forEach(function (person) {
                person.x = person.share * person.lane.loop;
            });
        }
        laidOutWidth = box.width;
        laidOutHeight = box.height;
        buildScenery();
    }

    /* ---------- Moving everyone on by one frame ---------- */

    function advance(seconds) {
        clock += seconds;
        var wish = pointerTempo !== null && clock - pointerAt < 5 ? pointerTempo : .56 + .13 * Math.sin(clock * .42);
        wish = clamp(wish + surge, 0, 1);
        tempo += (wish - tempo) * (1 - Math.exp(-seconds * 2.1));
        surge *= Math.exp(-seconds * 2.4);

        readGait(cameraGait, tempo);
        var cameraSpeed = cameraGait.stride / cameraGait.cycle;
        camera += cameraSpeed * seconds;

        for (var i = 0; i < people.length; i++) {
            var person = people[i], spec = person.spec, lane = person.lane;
            var wanted = clamp(tempo + spec.pace * lane.spread, .02, spec.maxPace || 1);
            person.effort += (wanted - person.effort) * (1 - Math.exp(-seconds * 3));
            var gait = person.pose.gait;
            readGait(gait, person.effort);
            var before = person.phase;
            person.phase = wrap(before + seconds / gait.cycle, 1);
            var speed = gait.stride / gait.cycle * spec.height;
            person.x = wrap(person.x + (speed - cameraSpeed) * seconds * lane.pixels, lane.loop);
            person.enter *= Math.exp(-seconds * 1.5);
            person.step = person.phase < before || (before < .5 && person.phase >= .5);
        }
        for (var d = dust.length - 1; d >= 0; d--) {
            var mote = dust[d];
            mote.life -= seconds * 1.7;
            if (mote.life <= 0) {
                dust.splice(d, 1);
                continue;
            }
            mote.x += (mote.vx - cameraSpeed * mote.pixels) * seconds;
            mote.y += mote.vy * seconds;
            mote.vy *= Math.exp(-seconds * 3);
        }
    }
    function kickDust(person, screenX) {
        if (quality > 1 || person.effort < .34 || dust.length > 40) {
            return;
        }
        var lane = person.lane, reach = person.pose.gait.landX * lane.pixels * person.spec.height;
        for (var i = 0; i < 3; i++) {
            dust.push({
                x: screenX + reach + (Math.random() - .5) * 6,
                y: lane.y - Math.random() * 3,
                vx: (Math.random() - .3) * 26 * lane.scale,
                vy: -(14 + Math.random() * 34) * lane.scale,
                r: (1.4 + Math.random() * 2.6) * lane.scale * (unit / 170),
                life: 1,
                pixels: lane.pixels,
            });
        }
    }

    /* ---------- Painting ---------- */

    function paintLayer(ctx, layer, offset, bottom) {
        var x = -wrap(offset, layer.width);
        x = Math.round(x * pixelRatio) / pixelRatio;
        ctx.drawImage(layer.canvas, x, bottom - layer.height, layer.width, layer.height);
        if (x + layer.width < width) {
            ctx.drawImage(layer.canvas, x + layer.width, bottom - layer.height, layer.width, layer.height);
        }
    }
    function paintGround(ctx) {
        ctx.fillStyle = ROAD;
        ctx.fillRect(0, horizon, width, height - horizon);
        ctx.fillStyle = KERB;
        ctx.fillRect(0, horizon, width, Math.max(3, roadHeight * .022));

        /* The words on the road slide at the speed of the road beneath them */
        var wordsLane = lerp(LANES[2].pixels, LANES[3].pixels, .5);
        paintLayer(ctx, roadWords, camera * wordsLane, horizon + roadHeight * .76);

        ctx.fillStyle = ROAD_DASH;
        var dashY = horizon + roadHeight * .31;
        var dashLength = unit * .34, dashGap = unit * .3;
        var start = -wrap(camera * lerp(LANES[1].pixels, LANES[2].pixels, .6), dashLength + dashGap);
        for (var x = start; x < width; x += dashLength + dashGap) {
            ctx.fillRect(x, dashY, dashLength, Math.max(2, roadHeight * .016));
        }
    }
    /* The sun is behind everyone, so shadows fall towards us and fan out on either side of it */
    function shadowLean(x) {
        return clamp((x - sunX) / (width * .55), -1.2, 1.2) * 1.15;
    }
    function paintBlockShadow(ctx) {
        var middle = (blockLeft + blockRight) / 2;
        var lean = shadowLean(middle) * blockHeight;
        var length = blockHeight * .42;
        ctx.fillStyle = SHADOW;
        ctx.beginPath();
        ctx.moveTo(blockLeft, horizon);
        ctx.lineTo(blockRight, horizon);
        ctx.lineTo(blockRight + lean, horizon + length);
        ctx.lineTo(blockLeft + lean, horizon + length);
        ctx.closePath();
        ctx.fill();
    }
    function paintShadow(ctx, person, screenX) {
        var size = person.lane.pixels * person.spec.height * pixelRatio;
        var lean = shadowLean(screenX);
        ctx.setTransform(size, 0, size * lean, size * .42, screenX * pixelRatio, person.lane.y * pixelRatio);
        traceSilhouette(ctx, person);
        ctx.fill();
    }
    function paintPerson(ctx, person, screenX) {
        var size = person.lane.pixels * person.spec.height * pixelRatio;
        ctx.setTransform(size, 0, 0, -size, screenX * pixelRatio, person.lane.y * pixelRatio);
        if (person.lane.detail === 0) {
            ctx.fillStyle = FAR_CROWD;
            traceSilhouette(ctx, person);
            ctx.fill();
        } else {
            drawPerson(ctx, person, person.kit, person.lane.detail);
        }
    }
    function screenXOf(person) {
        return person.x - person.lane.margin - person.enter * width;
    }
    function paint() {
        var i, person, screenX;
        for (i = 0; i < people.length; i++) {
            person = people[i];
            posePerson(person, person.phase, person.effort);
        }

        back.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        back.clearRect(0, 0, width, height);
        paintLayer(back, hills, camera * unit / 100 * .018, horizon);
        paintLayer(back, city, camera * unit / 100 * .06 + city.width * .02, horizon);
        paintLayer(back, roofs, camera * unit / 100 * .17, horizon);
        paintGround(back);
        paintBlockShadow(back);

        back.fillStyle = SHADOW;
        for (i = 0; i < people.length; i++) {
            person = people[i];
            screenX = screenXOf(person);
            if (person.lane.shadow && (quality < 2 || person.lane.front) && screenX > -width * .4 && screenX < width * 1.4) {
                paintShadow(back, person, screenX);
            }
        }

        front.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        front.clearRect(0, 0, width, height);
        for (i = 0; i < people.length; i++) {
            person = people[i];
            screenX = screenXOf(person);
            if (screenX < -person.lane.margin || screenX > width + person.lane.margin) {
                continue;
            }
            paintPerson(person.lane.front ? front : back, person, screenX);
            if (person.step && person.lane.front) {
                kickDust(person, screenX);
            }
        }
        back.setTransform(1, 0, 0, 1, 0, 0);

        front.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        front.fillStyle = "#C9C4B6";
        for (i = 0; i < dust.length; i++) {
            var mote = dust[i];
            front.globalAlpha = Math.min(1, mote.life * 1.4);
            front.beginPath();
            front.arc(mote.x, mote.y, mote.r * (1.5 - mote.life * .5), 0, TAU);
            front.fill();
        }
        front.globalAlpha = 1;
    }
    function showPace() {
        if (!paceOut) {
            return;
        }
        var pace = paceText(tempo);
        if (pace.time !== shownPace) {
            shownPace = pace.time;
            paceOut.textContent = pace.time;
            bandOut.textContent = paceBand(pace.seconds);
        }
    }

    /* ---------- Running, pausing and looking after slower phones ---------- */

    /*
     * Step the picture down only when drawing itself is too slow, or frames arrive below about 25 a second.
     * A steady 30 a second is fine: phones in battery saver do that however little there is to draw.
     */
    function checkSpeed(interval, drawing) {
        if (clock < 3 || quality >= 2) {
            return;
        }
        sampledFrames++;
        if (interval > 40 || drawing > 14) {
            slowFrames++;
        }
        if (sampledFrames >= 90) {
            if (slowFrames > 45) {
                quality++;
                layout();
            }
            sampledFrames = 0;
            slowFrames = 0;
        }
    }
    function frame(now) {
        frameRequest = requestAnimationFrame(frame);
        var interval = lastFrame ? now - lastFrame : 16;
        lastFrame = now;
        var startedAt = performance.now();
        advance(Math.min(interval / 1000, .05));
        paint();
        var drawing = performance.now() - startedAt;
        showPace();
        checkSpeed(interval, drawing);
    }
    function start() {
        if (running || paused || !onScreen || document.hidden || reduceMotion) {
            return;
        }
        running = true;
        lastFrame = 0;
        frameRequest = requestAnimationFrame(frame);
    }
    function stop() {
        running = false;
        cancelAnimationFrame(frameRequest);
    }

    layout();
    paint();
    hero.classList.add("is-live");

    if (document.fonts && document.fonts.load) {
        document.fonts.load('italic 900 40px "Anybody"').then(function () {
            buildRoadWords();
            if (!running) {
                paint();
            }
        });
    }

    var resizeTimer;
    new ResizeObserver(function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            var box = hero.getBoundingClientRect();
            if (box.width !== laidOutWidth || box.height !== laidOutHeight) {
                layout();
                paint();
            }
        }, 120);
    }).observe(hero);

    if (reduceMotion) {
        return;
    }

    hud.hidden = false;
    start();

    new IntersectionObserver(function (entries) {
        onScreen = entries[0].isIntersecting;
        if (onScreen) { start(); } else { stop(); }
    }).observe(hero);
    document.addEventListener("visibilitychange", function () {
        if (document.hidden) { stop(); } else { start(); }
    });

    /* Across the hero, left to right, is slowest to fastest */
    hero.addEventListener("pointermove", function (event) {
        var box = hero.getBoundingClientRect();
        pointerTempo = lerp(.04, 1, clamp((event.clientX - box.left) / box.width, 0, 1));
        pointerAt = clock;
    });
    hero.addEventListener("pointerleave", function () { pointerTempo = null; });

    /* Scrolling makes everyone pick up speed for a moment, and lifts the sun */
    window.addEventListener("scroll", function () {
        var y = window.scrollY;
        surge = Math.min(.42, surge + Math.abs(y - lastScrollY) * .0007);
        lastScrollY = y;
        if (onScreen) {
            hero.style.setProperty("--hero-scroll", y.toFixed(1));
        }
    }, { passive: true });

    pauseButton.addEventListener("click", function () {
        paused = !paused;
        pauseButton.setAttribute("aria-pressed", paused ? "true" : "false");
        pauseButton.setAttribute("aria-label", paused ? "Start the runners again" : "Pause the runners");
        if (paused) { stop(); } else { start(); }
    });
}
