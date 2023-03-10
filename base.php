<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Poptropica</title>
        <link rel="icon" href="/favicon.ico">
        <script id="input" type="application/json"><?php echo htmlspecialchars(json_encode($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET), ENT_HTML401); ?></script>
        <style>

body, embed { background-color: #139ffd; }

#errorText {
    position: absolute;
    top: 50vh;
    left: 0;
    width: 100vw;
    transform: translate(0, -50%);
}

#errorText * {
    color: white;
    font-size: 2em;
    font-family: "Billy Serif";
    text-align: center;
}

.fakeButton { text-decoration: underline; }
.fakeButton:hover { cursor: pointer; }

@font-face {
    font-family: "Billy Serif";
    src: url(/flashpoint/billySerif.ttf) format("truetype");
}

embed {
    outline-width: 0;
    position: absolute;
}

        </style>
    </head>
    <body>
        <div id="errorText" hidden>
            <div>Sorry, but common</div>
            <div>rooms are unavailable.</div>
            <div class="fakeButton" onclick="history.back();">Back</div>
        </div>
        <embed id="game" scale="noscale" wmode="gpu" menu="false" bgcolor="139ffd" hidden>
        <form method="POST">
            <input type="hidden" name="room">
            <input type="hidden" name="island">
            <input type="hidden" name="startup_path">
        </form>
        <script>
"use strict";

const SCENE_COMMON_EARLY = "Arcade",
      SCENE_FP_RESTART = "FlashpointIslandRestart",
      SCENE_FP_START = "Home"; // The real starting scene.

const STATE_COMMON = -1,
      STATE_SCENE = 0,
      STATE_FP_AD = 1,
      STATE_FP_RESTART = 2,
      STATE_FP_START = 3,
      STATE_FP_CREATE = 4;

const SWF_STATES = [ "/framework.swf", "/flashpoint/adSkip.swf", "/flashpoint/restartIsland.swf", "/flashpoint/saveData.swf", "/flashpoint/createUser.swf" ];

const COOKIE_ADS = "ads",
      COOKIE_NEW_USER = "ready";

const PATH_DEFAULT = "gameplay";

const game = document.getElementById("game"),
      errorText = document.getElementById("errorText"),
      lsKey = "lastScene";

main();

function main() {
    const params = getInput();
    flashpointLoad(params.island, params.room, params.startup_path);
}

function flashpointLoad(island, scene, path = PATH_DEFAULT) {
    let adScene = false,
        pageState;

    switch(scene) {
        case SCENE_COMMON_EARLY:
            // Necessary because Wimpy Boardwalk also has a scene named "Arcade". Compare the island to Wimpy Boardwalk rather than Early Poptropica to keep the behavior consistent with the other common room checks.
            pageState = (island === 'Boardwalk') ? STATE_SCENE : STATE_COMMON;
            break;
        case SCENE_FP_RESTART:
            pageState = STATE_FP_RESTART;
            break;
        case SCENE_FP_START:
            pageState = STATE_FP_START;
            break;
        default:
            if(scene.substring(0, 2) === 'Ad')
                if(getCookieFlag(COOKIE_ADS, true)) {
                    pageState = STATE_SCENE;
                    adScene = true;
                } else
                    pageState = STATE_FP_AD;
            else {
                const SPECIAL_COMMONS = [ "Coconut", "Party", "Cinema", "News", "HairClub", "Airlines", "Saltys", "Crop", "BaguetteInn", "Billiards", "MidasGym", "BrokenBarrel", "HotelInterior", "ClubInterior" ];

                if(!scene.includes("Common") && !SPECIAL_COMMONS.includes(scene))
                    if(getCookieFlag(COOKIE_NEW_USER, false)) {
                        pageState = STATE_FP_CREATE;
                        setCookieFlag(COOKIE_NEW_USER, true);
                    } else
                        pageState = STATE_SCENE;
                else
                    pageState = STATE_COMMON;
            }

            break;
    }

    game.hidden = pageState === STATE_COMMON;
    errorText.hidden = !game.hidden;

    if(!game.hidden) {
        let width, height, gameState;

        if(adScene) {
            gameState = 'return_user_advertisement_1';
            width = 776;
            height = 480;
        } else if(scene === SCENE_FP_START) {
            gameState = 'init';
            width = 800;
            height = 480;
        } else {
            gameState = 'return_user_standard';
            width = 1010;
            height = 645;
        }

        const flashVars = new URLSearchParams();
        flashVars.set("desc", scene);
        flashVars.set("island", island);
        flashVars.set("startup_path", path);
        flashVars.set("state", gameState);

        game.width = width;
        game.height = height;
        game.style.left = `calc(50vw - ${ width }px / 2)`;
        game.style.top = `calc(50vh - ${ height }px / 2)`;
        game.setAttribute("flashvars", flashVars);

        if(pageState === STATE_FP_START)
            loadFPStart(SWF_STATES[pageState]);
        else {
            if(pageState === STATE_SCENE)
                sceneChange(island, scene);

            game.src = SWF_STATES[pageState];
        }
    }
}

function flashpointError(recoverable) {
    if(recoverable)
        location.reload();
    else {
        alert("A fatal error occurred.");
        location.href = "/";
    }
}

function loadFPStart(extraMenuSrc) {
    game.setAttribute("wmode", "opaque");

    const extraMenu = game.cloneNode();
    extraMenu.height = 50;
    extraMenu.src = extraMenuSrc;
    extraMenu.id = null;
    game.parentNode.insertBefore(extraMenu, game.nextElementSibling);

    window.flashpointLoad = function() {
        game.src = SWF_STATES[STATE_SCENE];
        game.hidden = extraMenu.hidden = false;
        extraMenu.style.top = `calc(50vh + ${ game.height }px / 2)`;
    };

    window.setInteractivity = function(disabled) {
        extraMenu.style.opacity = game.style.opacity = disabled ? 0.6 : null;
        extraMenu.style.pointerEvents = game.style.pointerEvents = disabled ? "none" : null;
    };

    window.loadTrackingPixel = function() {
        setInteractivity(true);
        extraMenu.newUser();
    };

    window.newUserAccept = function() {
        setCookieFlag(COOKIE_NEW_USER, false);
        finalize();
    };

    window.returnUser = function() {
        game.remove();
        setTimeout(function() { extraMenu.returnUser(); }, 1); // Wait until `game` has unloaded. `extraMenu.returnUser()` and `requestAnimationFrame(extraMenu.returnUser)` don't work in time for... reasons?
    };

    window.returnUserAccept = function() {
        let island = "Early",
            scene = "City2";
        const lastScene = getLastScene();

        if(lastScene) {
            island = lastScene.island;
            scene = lastScene.scene;
        }

        finalize();
        POSTToBase(scene, island, PATH_DEFAULT);
    };

    window.newUserReject = window.returnUserReject = function() {
        location.reload();
    };

    window.getAdStatus = function() {
        return getCookieFlag(COOKIE_ADS, false);
    };

    window.setAdStatus = function(disabled) {
        return setCookieFlag(COOKIE_ADS, disabled);
    };

    window.finalize = function() {
        setInteractivity(false);
        extraMenu.remove();
    };
}

// Utilities

function getInput() {
    let obj;

    try {
        obj = JSON.parse(document.getElementById("input").innerText);
    } catch(err) { }

    if(typeof obj !== "object" || obj === null || Array.isArray(obj))
        obj = { };

    if(typeof obj.room !== "string")
        obj.room = SCENE_FP_START;

    if(typeof obj.island !== "string")
        obj.island = "Home";

    if(typeof obj.startup_path !== "string")
        obj.startup_path = PATH_DEFAULT;

    return obj;
}

function sceneChange(island, scene) {
    try {
        localStorage.setItem(lsKey, JSON.stringify({ island, scene }));
    } catch(err) { }
}

function getLastScene() {
    try {
        const data = JSON.parse(localStorage.getItem(lsKey));

        if(typeof data === "object" && data !== null && typeof data.island === "string" && typeof data.scene === "string")
            return data;
    } catch(err) { }

    return null;
}

function getCookieFlag(name, defaultValue) { // Returns true if flag is ENABLED
    const res = new RegExp(`(^|;)\\s*${ name }=(.)`, "").exec(document.cookie);
    return res ? res[2] !== '0' : defaultValue;
}

function setCookieFlag(name, disabled) { // DISABLES flag
    document.cookie = `${ name }=${ disabled ? '0' : '1' };expires=${ new Date(Date.now() + 315576000000) };path=/`;
}

// Game functions

function dbug(message) {
    console.log(message);
}

function POSTToBase(...args) {
    const form = document.forms[0];

    if(form.children.length === args.length) {
        for(let i = 0; i < args.length; i++)
            form.children[i].setAttribute("value", args[i]);

        form.submit();
    }
}

        </script>
    </body>
</html>