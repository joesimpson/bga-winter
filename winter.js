/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * winter implementation : © joesimpson <1324811+joesimpson@users.noreply.github.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * winter.js
 *
 * winter user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */
//Tisaac way to debug ;)
var isDebug = window.location.host == 'studio.boardgamearena.com' || window.location.hash.indexOf('debug') > -1;
var debug = isDebug ? console.info.bind(window.console) : function () {};

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/scrollmap",
    g_gamethemeurl + 'modules/js/Core/game.js',
    g_gamethemeurl + 'modules/js/Core/modal.js',
],
function (dojo, declare) {
    //COnstants copied from PHP file
    const CARD_LOCATION_BOARD = 'board';
    const TOKEN_LOCATION_BOARD = 'board';

    const PREF_UNDO_STYLE = 101;
    const PREF_CONFIRM = 102;
    
    const TOKEN_COUNTER_BLUE_LIGHT = 1;
    const TOKEN_COUNTER_BLUE_DARK = 2;

    return declare("bgagame.winter", [customgame.game], {
        constructor: function(){
            debug('winter constructor');
              
            // Here, you can init the global variables of your user interface
            this._counters = {};
            
            //Filter states where we don't want other players to display state actions
            this._activeStates = ['startingCard','colorChoice','playerTurn','confirmTurn'];
            this._inactiveStates = ['scoring','gameEnd'];
        },
        
        ///////////////////////////////////////////////////
        //     _____ ______ _______ _    _ _____  
        //    / ____|  ____|__   __| |  | |  __ \ 
        //   | (___ | |__     | |  | |  | | |__) |
        //    \___ \|  __|    | |  | |  | |  ___/ 
        //    ____) | |____   | |  | |__| | |     
        //   |_____/|______|  |_|   \____/|_|    
        /////////////////////////////////////////////////// 
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        setup: function( gamedatas )
        {
            debug( "Starting game setup" );

            document.getElementById('game_play_area').insertAdjacentHTML('beforeend', `
                <div id="winter_game_container">
                    <div id="winter_overall_background"></div>
                    <div id="winter_main_zone">
                        <div id="winter_cards_deck_container">
                            <div class="winter_card_back">
                                <div class="winter_deck_size" id="winter_deck_size">${gamedatas.deckSize}</div>
                            </div>
                        </div>
                        <!-- BGA SCROLLMAP Component -->
                        <div id="winter_map_container">
                            <div id="winter_map_scrollable"></div>
                            <div id="winter_map_surface"></div>
                            <div id="winter_map_scrollable_oversurface">
                                <div id="winter_map_cards"></div> 
                                <div id="winter_map_card_places"></div> 
                                <div id="winter_map_tokens"></div> 
                            </div>
                        
                            <div class="movetop"></div> 
                            <div class="movedown"></div> 
                            <div class="moveleft"></div> 
                            <div class="moveright"></div> 

                        </div>
                    </div>
                    <div id="winter_players_table"></div>
                </div>
            `);
            /*
                        <div id="winter_map_footer" class="whiteblock">
                            <a href="#" id="enlargedisplay">↓  ${_("Enlarge display")}  ↓</a>
                        </div>
            */

            //ScrollMAP
            this.scrollmap = new ebg.scrollmap();
            this.scrollmap.create( 
                $('winter_map_container'),
                $('winter_map_scrollable'),
                $('winter_map_surface'),
                $('winter_map_scrollable_oversurface') 
            ); // use ids from template
            this.scrollmap.setupOnScreenArrows( 150 ); // this will hook buttons to onclick functions with 150px scroll step
            //dojo.connect( $('enlargedisplay'), 'onclick', this, 'onIncreaseDisplayHeight' );

            this.scrollmap.scrollto(-1*102,-1*158);  //1*card_width,1*card_height

            this.setupPlayers();
            this.setupInfoPanel();
            this.setupCards();
            this.setupTokens();

            this._counters['deckSize'] = this.createCounter('winter_deck_size',this.gamedatas.deckSize);
            this.addCustomTooltip(`winter_deck_size`, _('Cards in deck'));
 
            debug( "Ending specific game setup" );

            this.inherited(arguments);

            debug( "Ending game setup" );
        },
       
        /* not enough settings for now, let's keep all in 1 section
        getSettingsSections: ()=>({
            layout: _("Layout"),
            buttons: _("Buttons"),
        }),
        */
        getSettingsConfig() {
            return {

                confirmMode: { 
                    type: 'pref', 
                    prefId: PREF_CONFIRM },
                undoStyle: { 
                    type: 'pref', 
                    prefId: PREF_UNDO_STYLE },
            };
        },

        ///////////////////////////////////////////////////
        //     _____ _______    _______ ______  _____ 
        //    / ____|__   __|/\|__   __|  ____|/ ____|
        //   | (___    | |  /  \  | |  | |__  | (___  
        //    \___ \   | | / /\ \ | |  |  __|  \___ \ 
        //    ____) |  | |/ ____ \| |  | |____ ____) |
        //   |_____/   |_/_/    \_\_|  |______|_____/ 
        ///////////////////////////////////////////////////
        ///////////////////////////////////////////////////
        //// Game & client states

        onLeavingState(stateName) {
            this.inherited(arguments);
            this.empty('winter_map_card_places');
        },

        onEnteringStateStartingCard(args){
            debug('onEnteringStateStartingCard', args);

            const card = args.card;
            const playableDirs = args.playableDir;
            const playableCoords = args.playableCoords;
 
            this.chosenDir = playableDirs[0];
            this.addPrimaryActionButton(`btnRotateDir`, '<i class="fa6 fa6-rotate winter_icon_rotate"></i>' + _(`Rotate card`), () => {
                this.chosenDir = playableDirs [ (playableDirs.indexOf(this.chosenDir) + 1) % playableDirs.length ];
                document.querySelectorAll('.winter_card_spot').forEach((oCard) => {
                    oCard.dataset.dir = this.chosenDir;
                });
            });

            Object.values(playableCoords).forEach( (playableCoord) => {
                let row = playableCoord[0];
                let col = playableCoord[1];

                //Coord buttons For debug only ?
                //this.addPrimaryActionButton(`btnCoord_${row}_${col}`, (`${row},${col}`), () => {
                //    this.performAction('actPlayStartingCard', { dir: this.chosenDir, row: row,  col: col});
                //});

                let spot = this.addSelectableCardSpot(card, row, col);
                let callbackSpotSelection = (evt) => {
                    this.performAction('actPlayStartingCard', { dir: this.chosenDir, row: row,  col: col});
                };
                this.onClick(`${spot.id}`, callbackSpotSelection);
            });

        },
        
        onEnteringStateColorChoice(args){
            debug('onEnteringStateColorChoice', args);

            let colors = args.playableColors;
            Object.values(colors).forEach( (color) => {
                let buttonId = `btnChooseColor_${color}`;
                let iconColor = this.formatIcon('flake_color-'+color);
                let buttonText = this.fsr(  ("${color}") , { color: iconColor } );
                let callbackColorSelection = (evt) => {
                    this.selectedColor = color;
                    this.performAction('actChooseColor', { color: this.selectedColor});
                };
                this.addImageActionButton(buttonId, `<div class='btnChooseColor' data-color='${color}'>${buttonText}</div>`, callbackColorSelection);
            });
        },

        onEnteringStatePlayerTurn(args){
            debug('onEnteringStatePlayerTurn', args);

            const playableCardsIds = args.playableCardsIds; // returned by the argPlayerTurn

            // Add test action buttons in the action status bar, simulating a card click:
            playableCardsIds.forEach(
                cardId => this.statusBar.addActionButton(_('Play card with id ${card_id}').replace('${card_id}', cardId), () => this.onCardClick(cardId))
            ); 

            this.statusBar.addActionButton(_('Pass'), () => this.bgaPerformAction("actPass"), { color: 'secondary' }); 

        },         
 
        ///////////////////////////////////////////////////
        //// Player's action
        
        /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */
        
        // Example:
        
        onCardClick: function( card_id )
        {
            debug( 'onCardClick', card_id );

            this.bgaPerformAction("actPlayCard", { 
                card_id,
            }).then(() =>  {                
                // What to do after the server call if it succeeded
                // (most of the time, nothing, as the game will react to notifs / change of state instead)
            });        
        },    

        
        //////////////////////////////////////////////////////////////
        //    _   _       _   _  __ _           _   _                 
        //   | \ | |     | | (_)/ _(_)         | | (_)                
        //   |  \| | ___ | |_ _| |_ _  ___ __ _| |_ _  ___  _ __  ___ 
        //   | . ` |/ _ \| __| |  _| |/ __/ _` | __| |/ _ \| '_ \/ __|
        //   | |\  | (_) | |_| | | | | (_| (_| | |_| | (_) | | | \__ \
        //   |_| \_|\___/ \__|_|_| |_|\___\__,_|\__|_|\___/|_| |_|___/
        //                                                            
        //    
        //////////////////////////////////////////////////////////////
        notif_clearTurn: async function(args)  {
            debug('notif_clearTurn: restarting turn/step', args);
            this.cancelLogs(args.notifIds);
        },
        notif_refreshUI: async function(args) {
            debug('notif_refreshUI: refreshing UI', args);
            this.clearPossible();
            this.refreshPlayersDatas(args.datas['players']);
            ['cards', 'tokens', 'deckSize',].forEach((value) => {
                this.gamedatas[value] = args.datas[value];
            });
            this.setupCards();
            this.setupTokens();
    
            this.forEachPlayer((player) => {
                let pId = player.id;
                this.scoreCtrl[pId].toValue(player.score);
                this._counters[pId].nbtokens.toValue(player.nbtokens);
            });
            this._counters['deckSize'].toValue(args.datas.deckSize);
        },

        
        notif_cardPlayed: async function(args) {
            debug('notif_cardPlayed...', args);
            let pcard = args.card;
            if (!$(`winter_card-${pcard.id}`)) this.addCard(pcard, this.getVisibleTitleContainer());
            await this.slide(`winter_card-${pcard.id}`, this.getCardContainer(pcard), { duration: 650,})
        },

        notif_newPlayerColor: async function(args) {
            debug('notif_newPlayerColor: receiving a new color', args);
            let pid = args.player_id;
            this.refreshPlayerColor(pid,args.player_color);

        },
        ///////////////////////////////////////////////////
        //    _    _ _   _ _     
        //   | |  | | | (_) |    
        //   | |  | | |_ _| |___ 
        //   | |  | | __| | / __|
        //   | |__| | |_| | \__ \
        //    \____/ \__|_|_|___/
        //                       
        ///////////////////////////////////////////////////
        
        clearPossible() {
            this.inherited(arguments);
            //SPECIFIC GAME elements to clear : 
        },
 
        // onIncreaseDisplayHeight: function(evt) {
        //     debug('Event: onIncreaseDisplayHeight');
        //     evt.preventDefault();
        	
        //     let cur_h = toint(dojo.style( $('winter_map_container'), 'height'));
        //     dojo.style($('winter_map_container'), 'height', (cur_h + 300) + 'px');
        // },

        ////////////////////////////////////////////////////////////
        // _____                          _   _   _
        // |  ___|__  _ __ _ __ ___   __ _| |_| |_(_)_ __   __ _
        // | |_ / _ \| '__| '_ ` _ \ / _` | __| __| | '_ \ / _` |
        // |  _| (_) | |  | | | | | | (_| | |_| |_| | | | | (_| |
        // |_|  \___/|_|  |_| |_| |_|\__,_|\__|\__|_|_| |_|\__, |
        //                                                 |___/
        ////////////////////////////////////////////////////////////
        
        //use bgaFormatText instead of format_string_recursive to inject images in logs
        bgaFormatText : function(log, args) {
            try {
                if (log && args && !args.processed) {
                    args.processed = true;

                    ///
                    let token_color = 'token_color';
                    let token_color_type = 'token_color_type';
                    if(token_color in args && token_color_type in args) {
                        args.token_color = this.formatIcon("flake_color-"+args.token_color_type);
                        args.token_color_type = "";
                    }

                }
            } catch (e) {
                console.error(log,args,"Exception thrown", e.stack);
            }
            return { log, args };
        },

        formatIcon(name, n = null) {
            let type = name;
            let text = n == null ? '' : `<span class='winter_icon_qty' data-value="${n}">${n}</span>`;
            return `<div class="winter_icon_container winter_icon_container_${type}">
                <div class="winter_icon winter_icon_${type}">${text}</div>
                </div>`;
        },
        formatIconWithMultiImages(name, nbSubIcons = null, filterSubIconType = null, n = null) {
            let type = name;
            let tplSubIcons ='';
            if(nbSubIcons && nbSubIcons > 0){
                for(let k = 1; k<=nbSubIcons; k++){
                    if(filterSubIconType != null && k!= filterSubIconType) continue;
                    tplSubIcons +=`<div class='winter_subicon_${type}' data-type='${k}'></div>`;
                }
            }
            let text = n == null ? '' : `<span>${n}</span>`;
            return `<div class="winter_icon_container winter_icon_container_${type}">
                <div class="winter_icon winter_icon_${type}">${text}${tplSubIcons}</div>
                </div>`;
        },
        ////////////////////////////////////////
        //  ____  _
        // |  _ \| | __ _ _   _  ___ _ __ ___
        // | |_) | |/ _` | | | |/ _ \ '__/ __|
        // |  __/| | (_| | |_| |  __/ |  \__ \
        // |_|   |_|\__,_|\__, |\___|_|  |___/
        //                |___/
        ////////////////////////////////////////

        setupPlayers() {
            let currentPlayerNo = 1;
            let nPlayers = 0;
            this.forEachPlayer((player) => {
                let isCurrent = player.id == this.player_id;
                //let divPanel = `player_panel_content_${player.color}`;
                //this.place('tplPlayerPanel', player, divPanel, 'after');
                this.getPlayerPanelElement(player.id).insertAdjacentHTML('beforeend', this.tplPlayerPanel(player) );
                
                let pId = player.id;
                nPlayers++;
                if (isCurrent) currentPlayerNo = player.no;
 
                this._counters[pId] = {
                    nbtokens: this.createCounter(`winter_counter_${pId}_tokens`, player.nbtokens),
                };
            });
    
        },

        ////////////////////////////////////////////////////////
        //  ___        __         ____                  _
        // |_ _|_ __  / _| ___   |  _ \ __ _ _ __   ___| |
        //  | || '_ \| |_ / _ \  | |_) / _` | '_ \ / _ \ |
        //  | || | | |  _| (_) | |  __/ (_| | | | |  __/ |
        // |___|_| |_|_|  \___/  |_|   \__,_|_| |_|\___|_|
        ////////////////////////////////////////////////////////
        setupInfoPanel() {
            debug("setupInfoPanel");
                    
            dojo.place(this.tplConfigPlayerBoard(), 'player_boards', 'first');
            
            let chk = $('help-mode-chk');
            dojo.connect(chk, 'onchange', () => this.toggleHelpMode(chk.checked));
            this.addTooltip('help-mode-switch', '', _('Toggle help/safe mode.'));
  
            this._settingsModal = new customgame.modal('showSettings', {
                class: 'winter_popin',
                closeIcon: 'fa-times',
                title: _('Settings'),
                closeAction: 'hide',
                verticalAlign: 'flex-start',
                contentsTpl: `<div id='winter_settings'>
                    <div id='winter_settings_header'></div>
                    <div id="settings-controls-container"></div>
                </div>`,
            });
        },
        
        tplConfigPlayerBoard() {
            return `
            <div class='player-board' id="player_board_config">
                <div id="player_config" class="player_board_content">
                <div class="player_config_row" id="turn_counter_wrapper">
                </div>
                <div class="player_config_row">
                    <div id="help-mode-switch">
                        <input type="checkbox" class="checkbox" id="help-mode-chk" />
                        <label class="label" for="help-mode-chk">
                            <div class="ball"></div>
                        </label>
                        <svg aria-hidden="true" focusable="false" data-prefix="fad" data-icon="question-circle" class="svg-inline--fa fa-question-circle fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><g class="fa-group"><path class="fa-secondary" fill="currentColor" d="M256 8C119 8 8 119.08 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm0 422a46 46 0 1 1 46-46 46.05 46.05 0 0 1-46 46zm40-131.33V300a12 12 0 0 1-12 12h-56a12 12 0 0 1-12-12v-4c0-41.06 31.13-57.47 54.65-70.66 20.17-11.31 32.54-19 32.54-34 0-19.82-25.27-33-45.7-33-27.19 0-39.44 13.14-57.3 35.79a12 12 0 0 1-16.67 2.13L148.82 170a12 12 0 0 1-2.71-16.26C173.4 113 208.16 90 262.66 90c56.34 0 116.53 44 116.53 102 0 77-83.19 78.21-83.19 106.67z" opacity="0.4"></path><path class="fa-primary" fill="currentColor" d="M256 338a46 46 0 1 0 46 46 46 46 0 0 0-46-46zm6.66-248c-54.5 0-89.26 23-116.55 63.76a12 12 0 0 0 2.71 16.24l34.7 26.31a12 12 0 0 0 16.67-2.13c17.86-22.65 30.11-35.79 57.3-35.79 20.43 0 45.7 13.14 45.7 33 0 15-12.37 22.66-32.54 34C247.13 238.53 216 254.94 216 296v4a12 12 0 0 0 12 12h56a12 12 0 0 0 12-12v-1.33c0-28.46 83.19-29.67 83.19-106.67 0-58-60.19-102-116.53-102z"></path></g></svg>
                    </div> 
                    <div id="show-settings">
                    <svg  xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                        <g>
                        <path class="fa-secondary" fill="currentColor" d="M638.41 387a12.34 12.34 0 0 0-12.2-10.3h-16.5a86.33 86.33 0 0 0-15.9-27.4L602 335a12.42 12.42 0 0 0-2.8-15.7 110.5 110.5 0 0 0-32.1-18.6 12.36 12.36 0 0 0-15.1 5.4l-8.2 14.3a88.86 88.86 0 0 0-31.7 0l-8.2-14.3a12.36 12.36 0 0 0-15.1-5.4 111.83 111.83 0 0 0-32.1 18.6 12.3 12.3 0 0 0-2.8 15.7l8.2 14.3a86.33 86.33 0 0 0-15.9 27.4h-16.5a12.43 12.43 0 0 0-12.2 10.4 112.66 112.66 0 0 0 0 37.1 12.34 12.34 0 0 0 12.2 10.3h16.5a86.33 86.33 0 0 0 15.9 27.4l-8.2 14.3a12.42 12.42 0 0 0 2.8 15.7 110.5 110.5 0 0 0 32.1 18.6 12.36 12.36 0 0 0 15.1-5.4l8.2-14.3a88.86 88.86 0 0 0 31.7 0l8.2 14.3a12.36 12.36 0 0 0 15.1 5.4 111.83 111.83 0 0 0 32.1-18.6 12.3 12.3 0 0 0 2.8-15.7l-8.2-14.3a86.33 86.33 0 0 0 15.9-27.4h16.5a12.43 12.43 0 0 0 12.2-10.4 112.66 112.66 0 0 0 .01-37.1zm-136.8 44.9c-29.6-38.5 14.3-82.4 52.8-52.8 29.59 38.49-14.3 82.39-52.8 52.79zm136.8-343.8a12.34 12.34 0 0 0-12.2-10.3h-16.5a86.33 86.33 0 0 0-15.9-27.4l8.2-14.3a12.42 12.42 0 0 0-2.8-15.7 110.5 110.5 0 0 0-32.1-18.6A12.36 12.36 0 0 0 552 7.19l-8.2 14.3a88.86 88.86 0 0 0-31.7 0l-8.2-14.3a12.36 12.36 0 0 0-15.1-5.4 111.83 111.83 0 0 0-32.1 18.6 12.3 12.3 0 0 0-2.8 15.7l8.2 14.3a86.33 86.33 0 0 0-15.9 27.4h-16.5a12.43 12.43 0 0 0-12.2 10.4 112.66 112.66 0 0 0 0 37.1 12.34 12.34 0 0 0 12.2 10.3h16.5a86.33 86.33 0 0 0 15.9 27.4l-8.2 14.3a12.42 12.42 0 0 0 2.8 15.7 110.5 110.5 0 0 0 32.1 18.6 12.36 12.36 0 0 0 15.1-5.4l8.2-14.3a88.86 88.86 0 0 0 31.7 0l8.2 14.3a12.36 12.36 0 0 0 15.1 5.4 111.83 111.83 0 0 0 32.1-18.6 12.3 12.3 0 0 0 2.8-15.7l-8.2-14.3a86.33 86.33 0 0 0 15.9-27.4h16.5a12.43 12.43 0 0 0 12.2-10.4 112.66 112.66 0 0 0 .01-37.1zm-136.8 45c-29.6-38.5 14.3-82.5 52.8-52.8 29.59 38.49-14.3 82.39-52.8 52.79z" opacity="0.4"></path>
                        <path class="fa-primary" fill="currentColor" d="M420 303.79L386.31 287a173.78 173.78 0 0 0 0-63.5l33.7-16.8c10.1-5.9 14-18.2 10-29.1-8.9-24.2-25.9-46.4-42.1-65.8a23.93 23.93 0 0 0-30.3-5.3l-29.1 16.8a173.66 173.66 0 0 0-54.9-31.7V58a24 24 0 0 0-20-23.6 228.06 228.06 0 0 0-76 .1A23.82 23.82 0 0 0 158 58v33.7a171.78 171.78 0 0 0-54.9 31.7L74 106.59a23.91 23.91 0 0 0-30.3 5.3c-16.2 19.4-33.3 41.6-42.2 65.8a23.84 23.84 0 0 0 10.5 29l33.3 16.9a173.24 173.24 0 0 0 0 63.4L12 303.79a24.13 24.13 0 0 0-10.5 29.1c8.9 24.1 26 46.3 42.2 65.7a23.93 23.93 0 0 0 30.3 5.3l29.1-16.7a173.66 173.66 0 0 0 54.9 31.7v33.6a24 24 0 0 0 20 23.6 224.88 224.88 0 0 0 75.9 0 23.93 23.93 0 0 0 19.7-23.6v-33.6a171.78 171.78 0 0 0 54.9-31.7l29.1 16.8a23.91 23.91 0 0 0 30.3-5.3c16.2-19.4 33.7-41.6 42.6-65.8a24 24 0 0 0-10.5-29.1zm-151.3 4.3c-77 59.2-164.9-28.7-105.7-105.7 77-59.2 164.91 28.7 105.71 105.7z"></path>
                        </g>
                    </svg>
                    </div>
                </div>
            </div>
            `;
        },
        tplPlayerPanel(player) {
            return `<div class='winter_panel'>
            <div class="winter_first_player_holder"></div>
            <div class='winter_player_infos'>
                <div class='winter_player_resource_line'>
                    ${this.tplResourceCounter(player, 'tokens')}
                </div>
            </div>
            </div>`;
        },
        /**
         * Use this tpl for any counters that represent qty of tokens
         */
        tplResourceCounter(player, res, nbSubIcons = null, totalValue = null) {
            let totalText = totalValue ==null ? '' : `<span id='winter_counter_${player.id}_${res}_total' class='winter_resource_${res}_total'>${totalValue}</span> `;
            return `
            <div class='winter_player_resource winter_resource_${res}'>
                <span id='winter_counter_${player.id}_${res}' 
                class='winter_resource_${res}'></span>${totalText}${ nbSubIcons!=null ? this.formatIconWithMultiImages(res, nbSubIcons) : this.formatIcon(res, null)}
                <div class='winter_reserve' id='winter_reserve_${player.id}_${res}'></div>
            </div>
            `;
        },
        
        refreshPlayerColor(pid,color) {
            debug("refreshPlayerColor",pid,color);
            //update player color :
            this.gamedatas.players[pid].color = color;
            this.gamedatas.players[pid].color_back = (color == "ffffff") ? "bbbbbb" : null;
            let divSidePanel = $(`overall_player_board_${pid}`);
            divSidePanel.dataset.color = color;
            let divName = divSidePanel.querySelector(`#player_name_${pid}`).querySelector(`a:first-child` );
            divName.style.color = ` #${color}`;
            divName.dataset.color = color;
            //Remove previous (useful for debug UI):
            let old = Array.from(divName.classList).filter(element => element.startsWith("winter_playername_"));
            old.forEach(function(oldClass) {
                divName.classList.remove(oldClass);
            });
            //Add new
            divName.classList.add(`winter_playername_${color}`);
            
            //TODO JSA Update player panel icon
        },

        ////////////////////////////////////////////////////////
        //    ____              _
        //   / ___|__ _ _ __ __| |___
        //  | |   / _` | '__/ _` / __|
        //  | |__| (_| | | | (_| \__ \
        //   \____\__,_|_|  \__,_|___/
        //////////////////////////////////////////////////////////

        // This function is refreshUI compatible
        setupCards() {
            debug("setupCards");
            //destroy previous cards
            document.querySelectorAll('.winter_card[id^="winter_card-"]').forEach((oCard) => {
                this.destroy(oCard);
            });
            let cardIds = this.gamedatas.cards.map((card) => {
                let divCardId = `winter_card-${card.id}`;
                if (!$(divCardId)) {
                    this.addCard(card);
                }
        
                let o = $(divCardId);
                if (!o) return null;
        
                let container = this.getCardContainer(card);
                if (o.parentNode != $(container)) {
                    dojo.place(o, container);
                }
                return card.id;
            });
        },
    
        addCard(card, location = null) {
            debug('addCard',card);
            if ($('winter_card-' + card.id)) return;
    
            let o = this.place('tplCard', card, location == null ? this.getCardContainer(card) : location);
            let tooltipDesc = this.getCardTooltip(card);
            if (tooltipDesc != null) {
                this.addCustomTooltip(o.id, tooltipDesc);
            }
    
            return o;
        },
    
        getCardTooltip(card) {
            let cardDatas = card;
            let title = "";
            {
                title = _("Card");
            }
            let div = this.tplCard(cardDatas,'_tmp');
            return [`<div class='winter_card_tooltip'>
                    <div class="winter_h1">${title}</div>
                    <hr/>
                    <div class="winter_h3">${
                        (card.row == null || card.col == null ) ? "" :
                        this.fsr(_("Coordinates : ${row}, ${col}"), {row: card.row, col: card.col})
                    }</div>
                    ${div}
                    <div class="winter_h5">${this.fsr(_("type : #${value}"), {value: card.type})}</div>
                </div>`];
        }, 

        tplCard(card, prefix ='') {
            return `<div class="winter_card" id="winter_card${prefix}-${card.id}" data-id="${card.id}" data-type="${card.type}" data-dir="${card.dir}" data-row="${card.row}" data-col="${card.col}">
                    <div class="winter_card_wrapper">
                    </div>
                </div>`;
        },

        addSelectableCardSpot(card,row, column) {
            debug("addSelectableCardSpot", row, column);
            let spotDivId = `winter_card_spot_${row}_${column}`;
            if ( $(spotDivId) ) return $(spotDivId);
            
            let spot = this.place('tplCardSpot', {'card':card, 'row':row, 'column':column}, $("winter_map_card_places"));
    
            debug("addSelectableCardSpot() result=> ",spot);
            return spot;
        },
        tplCardSpot(datas) {
            return `<div class="winter_card_spot" id="winter_card_spot_${datas.row}_${datas.column}" data-type="${datas.card.type}" data-dir="1" data-row="${datas.row}" data-col="${datas.column}">
                </div>`;
        },
    
        getCardContainer(card) { 
            if( CARD_LOCATION_BOARD == card.location) {
                return $("winter_map_cards");//winter_map_scrollable_oversurface 
            }

            console.error('Trying to get container of a card', card);
            return 'winter_game_container';
        },

        
        ////////////////////////////////////////
        //  _______    _                   
        // |__   __|  | |                  
        //    | | ___ | | _____ _ __  ___  
        //    | |/ _ \| |/ / _ \ '_ \/ __| 
        //    | | (_) |   <  __/ | | \__ \ 
        //    |_|\___/|_|\_\___|_| |_|___/ 
        //                                
        ////////////////////////////////////////
        setupTokens(){
            debug('setupTokens');
            document.querySelectorAll('.winter_token[id^="winter_token-"]').forEach((oToken) => {
                this.destroy(oToken);
            });
            let tokenIds = this.gamedatas.tokens.map((token) => {
                this.addToken(token);
                return token.id;
            });
        },
        getTokenContainer(token) { 
            if( TOKEN_LOCATION_BOARD == token.location) {
                return $("winter_map_tokens");
            } 
            
            console.error('Trying to get container of a token', token);
            return 'winter_game_container';
        },
        
        addToken: function(token){
            console.log("addToken",token); 

            if ($(`winter_token-${token.id}`)) return $(`winter_token-${token.id}`);
    
            let obj = this.place('tplToken', token, this.getTokenContainer(token),'first'); 
            return obj;
        },
    
        tplToken(token, prefix ='') {
            if(token.type == TOKEN_COUNTER_BLUE_LIGHT 
            || token.type == TOKEN_COUNTER_BLUE_DARK) 
                return `<div class="winter_token winter_token_counter" id="winter_token${prefix}-${token.id}" data-type="${token.type}" data-row="${token.row}" data-col="${token.col}"></div>`;
            return '';
        },
   });             
});

//# sourceURL=winter.js