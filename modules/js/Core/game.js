/**
 * BGA framework override
 * AUTHOR : © Tisaac
 */
var isDebug = window.location.host == 'studio.boardgamearena.com' || window.location.hash.indexOf('debug') > -1;
var debug = isDebug ? console.info.bind(window.console) : function () {};

define(['dojo', 'dojo/_base/declare', g_gamethemeurl + 'modules/js/vendor/nouislider.min.js', 'ebg/core/gamegui'], (
  dojo,
  declare,
  noUiSlider
) => {
  const isPromise = (v) => typeof v === 'object' && typeof v.then === 'function';

  return declare('customgame.game', ebg.core.gamegui, {
    /*
     * Constructor
     */
    constructor() {
      this._notifications = [];
      this._activeStates = [];
      this._connections = [];
      this._selectableNodes = [];
      this._activeStatus = null;
      this._helpMode = false;
      this._dragndropMode = false;
      this._customTooltipIdCounter = 0;
      this._registeredCustomTooltips = {};

      this._notif_uid_to_log_id = {};
      this._notif_uid_to_mobile_log_id = {};
      this._last_notif = null;
      
      this._displayNotifsOnTop = true;
      this._displayNotifsOnTopWhenGameState = true;
      this._hideNotifsWhenMultiActive = false;
      this._displayRestartButtons = true;
      this.alwaysFixTopActions = true;
      //Max percentage of screen to use with top bar :
      this.alwaysFixTopActionsMaximum = 30;
    },

    destroyTooltip(elem) {
      if (this.tooltips[elem.id]) {
        clearTimeout(this.tooltips[elem.id].showTimeout);
        this.tooltips[elem.id].close();
        this.tooltips[elem.id].destroy();
        delete this.tooltips[elem.id];
      }
    },

    destroy(elem, delayRemove = false) {
      //debug(`destroy ${elem.id}`,elem);
      this.destroyTooltip(elem);
      this.empty(elem);
      if(!delayRemove) elem.remove();
    },
    
    empty(container) {
      //debug("empty",container);
      container = $(container);
      container.childNodes.forEach((node) => {
        //!! destroy node makes gap in LOOP because of removing them
        this.destroy(node,true);
      });
      container.childNodes.forEach((node) => {
        node.remove();
      });
      container.innerHTML = '';
    },

    showMessage(msg, type) {
      if (type == 'error') {
        console.error(msg);
        if (msg && msg.startsWith("!!!")) {
          if (msg == "!!!checkVersion") {
            this.infoDialog(  _("A new version of this game is now available"),_("Reload Required"), () => {window.location.reload(true);},true);
          }
          return; // suppress red banner and gamelog message
        }
      }
      return this.inherited(arguments);
    },

    isFastMode() {
      return this.instantaneousMode;
    },

    unsetModeInstantaneous() {
      this.inherited(arguments);
      this.updateLayout();
    },

    /*
     * [Undocumented] Override BGA framework functions to call onLoadingComplete when loading is done
     */
    setLoader(value, max) {
      this.inherited(arguments);
      if (!this.isLoadingComplete && value >= 100) {
        this.isLoadingComplete = true;
        this.onLoadingComplete();
      }
    },

    onLoadingComplete() {
      debug('Loading complete');
      //      this.cancelLogs(this.gamedatas.canceledNotifIds);
    },

    /*
     * Setup:
     */
    setup(gamedatas) {
      // Create a new div for buttons to avoid BGA auto clearing it
      dojo.place("<div id='customActions' style='display:inline-block'></div>", $('generalactions'), 'after');
      dojo.place("<div id='restartAction' style='display:inline-block'></div>", $('customActions'), 'after');

      dojo.place(`<svg style="display:none" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="map-marker-question" role="img" xmlns="http://www.w3.org/2000/svg">
          <symbol id="help-marker-svg" viewBox="0 0 512 512"><g class="fa-group"><path class="fa-secondary" fill="white" d="M256 8C119 8 8 119.08 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm0 422a46 46 0 1 1 46-46 46.05 46.05 0 0 1-46 46zm40-131.33V300a12 12 0 0 1-12 12h-56a12 12 0 0 1-12-12v-4c0-41.06 31.13-57.47 54.65-70.66 20.17-11.31 32.54-19 32.54-34 0-19.82-25.27-33-45.7-33-27.19 0-39.44 13.14-57.3 35.79a12 12 0 0 1-16.67 2.13L148.82 170a12 12 0 0 1-2.71-16.26C173.4 113 208.16 90 262.66 90c56.34 0 116.53 44 116.53 102 0 77-83.19 78.21-83.19 106.67z" opacity="1"></path><path class="fa-primary" fill="currentColor" d="M256 338a46 46 0 1 0 46 46 46 46 0 0 0-46-46zm6.66-248c-54.5 0-89.26 23-116.55 63.76a12 12 0 0 0 2.71 16.24l34.7 26.31a12 12 0 0 0 16.67-2.13c17.86-22.65 30.11-35.79 57.3-35.79 20.43 0 45.7 13.14 45.7 33 0 15-12.37 22.66-32.54 34C247.13 238.53 216 254.94 216 296v4a12 12 0 0 0 12 12h56a12 12 0 0 0 12-12v-1.33c0-28.46 83.19-29.67 83.19-106.67 0-58-60.19-102-116.53-102z"></path></g>
          </symbol>
      </svg>`, $('game_play_area'), 'last');
      
      this.attachRegisteredTooltips();

      this.setupNotifications();
      this.initPreferences();
      dojo.connect(this.notifqueue, 'addToLog', () => {
        this.checkLogCancel(this._last_notif == null ? null : this._last_notif.msg.uid);
        this.addLogClass();
      });
    },

    /*
     * Detect if spectator or replay
     */
    isReadOnly() {
      return this.isSpectator || typeof g_replayFrom != 'undefined' || g_archive_mode;
    },

    /*
     * Make an AJAX call with automatic lock
     */
    performAction(action, data, check = true, checkLock = true) {
      debug('performAction()',action, data, check, checkLock );
      if (check && !this.checkAction(action)) return false;
      if (!check && checkLock && !this.checkLock()) return false;

      data = data || {};
      let options = {};
      if (data.lock === undefined) {
        options.lock = true;
      } else if (data.lock === false) {
        delete data.lock;
      }
      delete data.lock;

      data.v = this.gamedatas.version;
      return this.bgaPerformAction(action, data);
    },

    /*
     * onEnteringState:
     * 	this method is called each time we are entering into a new game state.
     *
     * params:
     *  - str stateName : name of the state we are entering
     *  - mixed args : additional information
     */
    onEnteringState(stateName, args) {
      debug('Entering state: ' + stateName, args);
      if (this.isFastMode()) return;
      if (this._activeStates.includes(stateName) && !this.isCurrentPlayerActive()) return;

      // Restart turn button
      if (this._displayRestartButtons && args.args && args.args.previousChoices && args.args.previousChoices >= 1 && !args.args.automaticAction) {
        if (args.args && args.args.previousSteps) {
          let lastStep = Math.max(...args.args.previousSteps);
          if (lastStep > 0){
            this.addDangerActionButton('btnTextUndoLastStep', _('Undo last step'), () => this.undoToStep(lastStep), 'restartAction');
            this.addDangerActionButton('btnIconUndoLastStep', '<i class="fa fa-undo"></i>', () => this.undoToStep(lastStep), 'restartAction');
            this.addTooltip('btnIconUndoLastStep', '', _('Undo last step'));
          }
        }

        // Restart whole turn
        this.addDangerActionButton(
          'btnTextRestartTurn',
          _('Restart turn'),
          () => {
            //this.stopActionTimer();
            this.performAction('actRestart');
          },
          'restartAction'
        );
        this.addDangerActionButton(
          'btnIconRestartTurn',
          '<i class="fa fa-undo"></i><i class="fa fa-undo"></i>',
          () => {
            this.performAction('actRestart');
          },
          'restartAction'
        );
        this.addTooltip('btnIconRestartTurn', '', _('Restart turn'));
      }

      // Call appropriate method
      var methodName = 'onEnteringState' + stateName.charAt(0).toUpperCase() + stateName.slice(1);
      if (this[methodName] !== undefined) this[methodName](args.args);
    },

    /**
     * onLeavingState:
     * 	this method is called each time we are leaving a game state.
     *
     * params:
     *  - str stateName : name of the state we are leaving
     */
    onLeavingState(stateName) {
      debug('Leaving state: ' + stateName);
      if (this.isFastMode()) return;
      this.clearPossible();

      // Call appropriate method
      var methodName = 'onLeavingState' + stateName.charAt(0).toUpperCase() + stateName.slice(1);
      if (this[methodName] !== undefined) this[methodName]();
    },

    removeAllActionButtons() {
      this.removeActionButtons();
      dojo.empty('customActions');
      dojo.empty('restartAction');
    },
    
    undoToStep(stepId) {
      this.checkAction('actRestart');
      this.performAction('actUndoToStep', { stepId }, false);
    },

    clearPreAnimation() {
      debug('clearPreAnimation()' );
      this._connections.forEach(dojo.disconnect);
      this._connections = [];
      this._selectableNodes.forEach((node) => {
        if ($(node)) dojo.removeClass(node, 'selectable selected');
      });
      this._selectableNodes = [];
      dojo.query('.unselectable').removeClass('unselectable');
      dojo.query('.selectable').removeClass('selectable');
      dojo.query('.selected').removeClass('selected');
    },

    clearPossible() {
      debug('clearPossible()' );
      this.clearPreAnimation();
      this.removeAllActionButtons(); 
    },

    /**
     * Check change of activity
     */
    onUpdateActionButtons(stateName, args) {
      let status = this.isCurrentPlayerActive();
      if (status != this._activeStatus) {
        debug('Update activity: ' + stateName, status);
        this._activeStatus = status;

        // Call appropriate method
        var methodName = 'onUpdateActivity' + stateName.charAt(0).toUpperCase() + stateName.slice(1);
        if (this[methodName] !== undefined) this[methodName](args, status);
      }
    },

    getVisibleTitleContainer() {
      function isVisible(elem) {
        return !!(elem.offsetWidth || elem.offsetHeight || elem.getClientRects().length);
      }

      if (isVisible($('pagemaintitletext'))) {
        return $('pagemaintitletext');
      } else {
        return $('gameaction_status');
      }
    },
    setupNotifications() {
      console.log("setupNotifications",this._notifications); 
      /*
      this._notifications.forEach((notif) => {
        var functionName = 'notif_' + notif[0];

        let wrapper = (args) => {
          if(this._displayNotifsOnTop 
            && !(this.gamedatas.gamestate.type == 'multipleactiveplayer' && this._hideNotifsWhenMultiActive)
            || this.gamedatas.gamestate.type == 'game' && this._displayNotifsOnTopWhenGameState){
            let msg = this.format_string_recursive(args.log, args.args);
            if (msg != '') {
              $('gameaction_status').innerHTML = msg;
              $('pagemaintitletext').innerHTML = msg;
              this.removeAllActionButtons();
            }
          }
          let timing = this[functionName](args);
          if (timing === undefined) {
            if (notif[1] === undefined) {
              console.error("A notification don't have default timing and didn't send a timing as return value : " + notif[0]);
              return;
            }

            // Override default timing by 1 in case of fast replay mode
            timing = this.isFastMode() ? 0 : notif[1];
          }

          if (timing !== null && !isPromise(timing)) {
            this.notifqueue.setSynchronousDuration(timing);
          }
        };

        dojo.subscribe(notif[0], this, wrapper);
        this.notifqueue.setSynchronous(notif[0]);

        if (notif[2] != undefined) {
          this.notifqueue.setIgnoreNotificationCheck(notif[0], notif[2]);
        }
      });

      this.notifqueue.setSynchronousDuration = (duration) => {
        setTimeout(() => dojo.publish('notifEnd', null), duration);
      };
      */
     
      //2024 NEw Framework function
      this.bgaSetupPromiseNotifications( {
        minDuration: 900, // because slide 800
        //minDurationNoText: 500,
        logger: debug,
        onStart: (notifName, msg, args) => {
          if (this._displayNotifsOnTop && msg != '') {
            $('gameaction_status').innerHTML = msg;
            $('pagemaintitletext').innerHTML = msg;
            this.removeAllActionButtons();
          }
          this.clearPreAnimation();
        }, 
        onEnd: (notifName, msg, args) => { 
          //To see log 
        },
      });
    },

    /**
    Wrapper for setting a notification duration which may depends on player prefs/settings
    */
    setNotifDuration(time = 0){
      //if(!this.isSettingAnimationsEnabled()) time = 0;
      if(!this.bgaAnimationsActive()) time = 0;
      this.notifqueue.setSynchronousDuration(time);
    },

    /*
     * Add a timer on an action button :
     * params:
     *  - buttonId : id of the action button
     *  - time : time before auto click
     *  - pref : 0 is disabled (auto-click), 1 if normal timer, 2 if no timer and show normal button
     */

    startActionTimer(buttonId, time, pref, autoclick = false) {
      var button = $(buttonId);
      var isReadOnly = this.isReadOnly();
      if (button == null || isReadOnly || pref == 2) {
        debug('Ignoring startActionTimer(' + buttonId + ')', 'readOnly=' + isReadOnly, 'prefValue=' + pref);
        return;
      }

      // If confirm disabled, click on button
      if (pref == 0) {
        if (autoclick) button.click();
        return;
      }

      this._actionTimerLabel = button.innerHTML;
      this._actionTimerSeconds = time;
      this._actionTimerFunction = () => {
        var button = $(buttonId);
        if (button == null) {
          this.stopActionTimer();
        } else if (this._actionTimerSeconds-- > 1) {
          button.innerHTML = this._actionTimerLabel + ' (' + this._actionTimerSeconds + ')';
        } else {
          debug('Timer ' + buttonId + ' execute');
          button.click();
        }
      };
      this._actionTimerFunction();
      this._actionTimerId = window.setInterval(this._actionTimerFunction, 1000);
      debug('Timer #' + this._actionTimerId + ' ' + buttonId + ' start');
    },

    stopActionTimer() {
      if (this._actionTimerId != null) {
        debug('Timer #' + this._actionTimerId + ' stop');
        window.clearInterval(this._actionTimerId);
        delete this._actionTimerId;
      }
    },

    /*
     * Play a given sound that should be first added in the tpl file
     */
    playSound(sound, playNextMoveSound = true) {
      playSound(sound);
      playNextMoveSound && this.disableNextMoveSound();
    },

    resetPageTitle() {
      this.changePageTitle();
    },

    changePageTitle(suffix = null, save = false) {
      if (suffix == null) {
        suffix = 'generic';
      }

      if (!this.gamedatas.gamestate['descriptionmyturn' + suffix]) return;

      if (save) {
        this.gamedatas.gamestate.descriptionmyturngeneric = this.gamedatas.gamestate.descriptionmyturn;
        this.gamedatas.gamestate.descriptiongeneric = this.gamedatas.gamestate.description;
      }

      this.gamedatas.gamestate.descriptionmyturn = this.gamedatas.gamestate['descriptionmyturn' + suffix];
      if (this.gamedatas.gamestate['description' + suffix])
        this.gamedatas.gamestate.description = this.gamedatas.gamestate['description' + suffix];
      this.updatePageTitle();
    },

    onScreenWidthChange() {
      /*
       * Remove non standard zoom property
      dojo.style('page-content', 'zoom', '');
      dojo.style('page-title', 'zoom', '');
      dojo.style('right-side-first-part', 'zoom', '');
       */
    },

    /*
     * Add a blue/grey button if it doesn't already exists
     */
    addPrimaryActionButton(id, text, callback, zone = 'customActions') {
      this.addPrimaryActionButtonWithConfirm(id, text, callback, null, zone);
    },

    addPrimaryActionButtonWithConfirm(id, text, callback, confirmText, zone = 'customActions') {
      if (!$(id)) this.statusBar.addActionButton(text, callback, {'id': id, 'destination': $(zone), 'color': 'blue', 'confirm': confirmText, });
    },
    addSecondaryActionButton(id, text, callback, zone = 'customActions') {
      if (!$(id)) this.statusBar.addActionButton(text, callback, {'id': id, 'destination': $(zone), 'color': 'gray', });
    },

    addDangerActionButton(id, text, callback, zone = 'customActions') {
      if (!$(id)) this.statusBar.addActionButton(text, callback, {'id': id, 'destination': $(zone), 'color': 'red', });
    },
    /**
     * div_html is string not node
     */
    addImageActionButton(id, div_html, callback, zone = 'customActions') { 
      if (!$(id)) this.addActionButton(id, div_html, callback, zone, false, 'blue'); 
      dojo.style(id, "border", "none"); // remove ugly border
      dojo.addClass(id, "customimagebutton"); // add css class to do more styling
      return $(id); // return node for chaining
    },

    clearActionButtons() {
      debug( "clearActionButtons()" );
      dojo.empty('customActions');
    },

    /*
     * Preference polyfill
     */
    setPreferenceValue(number, newValue) {
      var optionSel = 'option[value="' + newValue + '"]';
      dojo
        .query('#preference_control_' + number + ' > ' + optionSel + ', #preference_fontrol_' + number + ' > ' + optionSel)
        .attr('selected', true);
      var select = $('preference_control_' + number);
      if (dojo.isIE) {
        select.fireEvent('onchange');
      } else {
        var event = document.createEvent('HTMLEvents');
        event.initEvent('change', false, true);
        select.dispatchEvent(event);
      }
    },

    initPreferencesObserver() {
      dojo.query('.preference_control, preference_fontrol').on('change', (e) => {
        var match = e.target.id.match(/^preference_[fc]ontrol_(\d+)$/);
        if (!match) {
          return;
        }
        var pref = match[1];
        var newValue = e.target.value;
        this.prefs[pref].value = newValue;
        if (this.prefs[pref].attribute) {
          $('ebd-body').setAttribute('data-' + this.prefs[pref].attribute, newValue);
        }

        $('preference_control_' + pref).value = newValue;
        if ($('preference_fontrol_' + pref)) {
          $('preference_fontrol_' + pref).value = newValue;
        }
        data = { pref: pref, lock: false, value: newValue, player: this.player_id };
        //if (!this.isReadOnly()) this.performAction('actChangePref', data, false, false);
        this.onPreferenceChange(pref, newValue);
      });
    },

    checkPreferencesConsistency(backPrefs) {
      backPrefs.forEach((prefInfo) => {
        let pref = prefInfo.pref_id;
        if (this.prefs[pref] != undefined && this.prefs[pref].value != prefInfo.pref_value) {
          data = { pref: pref, lock: false, value: this.prefs[pref].value, player: this.player_id };
          //this.performAction('actChangePref', data, false, false);
        }
      });
    },

    onPreferenceChange(pref, newValue) {},

    // Init preferences will setup local preference and put the corresponding data-attribute on overall-content div if needed
    initPreferences() {
      // Attach data attribute on overall-content div
      Object.keys(this.prefs).forEach((prefId) => {
        let pref = this.prefs[prefId];
        if (pref.attribute) {
          $('ebd-body').setAttribute('data-' + pref.attribute, pref.value);
        }
      });

      if (!this.isReadOnly() && this.gamedatas.localPrefs) {
        // Create local prefs
        Object.keys(this.gamedatas.localPrefs).forEach((prefId) => {
          let pref = this.gamedatas.localPrefs[prefId];
          pref.id = prefId;
          let selectedValue = this.gamedatas.prefs.find((pref2) => pref2.pref_id == pref.id).pref_value;
          pref.value = selectedValue;
          this.prefs[prefId] = pref;
          if (pref.attribute) {
            $('ebd-body').setAttribute('data-' + pref.attribute, selectedValue);
          }
          this.place('tplPreferenceSelect', pref, 'local-prefs-container');
        });
      }

      this.initPreferencesObserver();
      if (!this.isReadOnly()) {
        this.checkPreferencesConsistency(this.gamedatas.prefs);
      }

      this.setupSettings();
    },

    tplPreferenceSelect(pref) {
      let values = Object.keys(pref.values)
        .map(
          (val) => `<option value='${val}' ${pref.value == val ? 'selected="selected"' : ''}>${_(pref.values[val].name)}</option>`
        )
        .join('');

      return `
        <div class="preference_choice">
          <div class="row-data row-data-large">
            <div class="row-label">${_(pref.name)}</div>
            <div class="row-value">
              <select id="preference_control_${
                pref.id
              }" class="preference_control game_local_preference_control" style="display: block;">
                ${values}
              </select>
            </div>
          </div>
        </div>
      `;
    },

    onPreferenceChange(pref, newValue) {},

    /************************
     ******* SETTINGS ********
     ************************/
    setupSettings() {
      dojo.connect($('show-settings'), 'onclick', () => this.toggleSettings());
      this.addTooltip('show-settings', '', _('Display some settings about the game.'));
      let container = $('settings-controls-container');

      if (this.getSettingsSections) {
        this._settingsSections = this.getSettingsSections();
        dojo.place(`<div id='settings-controls-header'></div><div id='settings-controls-wrapper'></div>`, container);
        Object.keys(this._settingsSections).forEach((sectionName, i) => {
          dojo.place(`<div id='settings-section-${sectionName}' class='settings-section'></div>`, 'settings-controls-wrapper');
          let div = dojo.place(`<div>${this._settingsSections[sectionName]}</div>`, 'settings-controls-header');
          let openSection = () => {
            dojo.query('#settings-controls-header div').removeClass('open');
            div.classList.add('open');
            dojo.query('#settings-controls-wrapper div.settings-section').removeClass('open');
            $(`settings-section-${sectionName}`).classList.add('open');
          };
          div.addEventListener('click', openSection);
          if (i == 0) {
            openSection();
          }
        });
      }

      this.settings = {};
      this._settingsConfig = this.getSettingsConfig();
      Object.keys(this._settingsConfig).forEach((settingName) => {
        let config = this._settingsConfig[settingName];
        let localContainer = container;
        if (config.section) {
          localContainer = $(`settings-section-${config.section}`);
        }

        if (config.type == 'pref') {
          if (config.local == true && this.isReadOnly()) {
            return;
          }
          // Pref type => just move the user pref around
          dojo.place($('preference_control_' + config.prefId).parentNode.parentNode, localContainer);
          return;
        }

        let suffix = settingName.charAt(0).toUpperCase() + settingName.slice(1);
        let value = this.getConfig(this.game_name + suffix, config.default);
        this.settings[settingName] = value;

        // Slider type => create DOM and initialize noUiSlider
        if (config.type == 'slider') {
          this.place('tplSettingSlider', { desc: config.name, id: settingName }, localContainer);
          config.sliderConfig.start = [value];
          noUiSlider.create($('setting-' + settingName), config.sliderConfig);
          $('setting-' + settingName).noUiSlider.on('slide', (arg) => this.changeSetting(settingName, parseInt(arg[0])));
        } else if (config.type == 'multislider') {
          this.place('tplSettingSlider', { desc: config.name, id: settingName }, localContainer);
          config.sliderConfig.start = value;
          noUiSlider.create($('setting-' + settingName), config.sliderConfig);
          $('setting-' + settingName).noUiSlider.on('slide', (arg) => this.changeSetting(settingName, arg));
        }

        // Select type => create a select
        else if (config.type == 'select') {
          config.id = settingName;
          this.place('tplSettingSelect', config, localContainer);
          $('setting-' + settingName).addEventListener('change', () => {
            let newValue = $('setting-' + settingName).value;
            this.changeSetting(settingName, newValue);
            if (config.attribute) {
              $('ebd-body').setAttribute('data-' + config.attribute, newValue);
            }
          });
        }
        // Switch type => create a select
        else if (config.type == 'switch') {
          config.id = settingName;
          this.place('tplSettingSwitch', config, localContainer);
          $('setting-' + settingName).addEventListener('change', () => {
            let newValue = $('setting-' + settingName).checked ? 1 : 0;
            this.changeSetting(settingName, newValue);
            if (config.attribute) {
              $('ebd-body').setAttribute('data-' + config.attribute, newValue);
            }
          });
        }

        if (config.attribute) {
          $('ebd-body').setAttribute('data-' + config.attribute, value);
        }
        this.changeSetting(settingName, value);
      });
    },

    changeSetting(settingName, value) {
      let suffix = settingName.charAt(0).toUpperCase() + settingName.slice(1);
      this.settings[settingName] = value;
      localStorage.setItem(this.game_name + suffix, value);
      let methodName = 'onChange' + suffix + 'Setting';
      if (this[methodName]) {
        this[methodName](value);
      }
    },

    tplSettingSlider(setting) {
      return `
      <div class='row-data row-data-large' data-id='${setting.id}'>
        <div class='row-label'>${setting.desc}</div>
        <div class='row-value slider'>
          <div id="setting-${setting.id}"></div>
        </div>
      </div>
      `;
    },

    tplSettingSwitch(setting) {
      return `
      <div class='row-data row-data-large row-data-switch' data-id='${setting.id}'>
        <div class='row-label'>${_(setting.name)}</div>
        <div class='row-value'>
          <label class="switch" for="setting-${setting.id}">
            <input type="checkbox" id="setting-${setting.id}" ${this.settings[setting.id] == 1 ? 'checked="checked"' : ''} />
            <div class="slider round"></div>
          </label>
        </div>
      </div>
      `;
    },

    tplSettingSelect(setting) {
      let values = Object.keys(setting.values)
        .map(
          (val) =>
            `<option value='${val}' ${this.settings[setting.id] == val ? 'selected="selected"' : ''}>${_(
              setting.values[val]
            )}</option>`
        )
        .join('');

      return `
        <div class="preference_choice" data-id='${setting.id}'>
          <div class="row-data row-data-large">
            <div class="row-label">${_(setting.name)}</div>
            <div class="row-value">
              <select id="setting-${setting.id}" class="preference_control game_local_preference_control" style="display: block;">
                ${values}
              </select>
            </div>
          </div>
        </div>
      `;
    },

    toggleSettings() {
      this._settingsModal.show();
    },

    getScale(id) {
      let transform = dojo.style(id, 'transform');
      if (transform == 'none') return 1;

      var values = transform.split('(')[1];
      values = values.split(')')[0];
      values = values.split(',');
      let a = values[0];
      let b = values[1];
      return Math.sqrt(a * a + b * b);
    },
    /*11/2024 Framework version 
    wait(n) {
      return new Promise((resolve, reject) => {
        if (this.isFastMode()) {
          resolve();
        } else {
          setTimeout(() => resolve(), n);
        }
      });
    },*/

    slide: async function(mobileElt, targetElt, options = {}) {
      let config = Object.assign(
        {
          duration: 800,
          delay: 0,
          destroy: false,
          attach: true,
          changeParent: true, // Change parent during sliding to avoid zIndex issue
          pos: null,
          className: 'moving',
          from: null,
          clearPos: true,
          beforeBrother: null,
          to: null,

          phantom: true,
        },
        options
      );
      config.phantomStart = config.phantomStart || config.phantom;
      config.phantomEnd = config.phantomEnd || config.phantom;

      // Mobile elt
      mobileElt = $(mobileElt);
      let mobile = mobileElt;
      // Target elt
      targetElt = $(targetElt);
      let targetId = targetElt;
      const newParent = config.attach ? targetId : $(mobile).parentNode;

      // Handle fast mode
      if (!this.bgaAnimationsActive() && (config.destroy || config.clearPos)) {
        if (config.destroy) this.destroy(mobile);
        else dojo.place(mobile, targetElt);

        return new Promise((resolve, reject) => {
          resolve();
        });
      }

      // Handle phantom at start
      if (config.phantomStart && config.from == null) {
        mobile = dojo.clone(mobileElt);
        dojo.attr(mobile, 'id', mobileElt.id + '_animated');
        dojo.place(mobile, 'game_play_area');
        this.placeOnObject(mobile, mobileElt);
        dojo.addClass(mobileElt, 'phantom');
        config.from = mobileElt;
      }

      // Handle phantom at end
      if (config.phantomEnd) {
        targetId = dojo.clone(mobileElt);
        dojo.attr(targetId, 'id', mobileElt.id + '_afterSlide');
        dojo.addClass(targetId, 'phantom');
        if (config.beforeBrother != null) {
          dojo.place(targetId, config.beforeBrother, 'before');
        } else {
          dojo.place(targetId, targetElt);
        }
      }

      dojo.style(mobile, 'zIndex', 5000);
      dojo.addClass(mobile, config.className);
      if (config.changeParent) this.changeParent(mobile, 'game_play_area');
      if (config.from != null) this.placeOnObject(mobile, config.from);
      return new Promise(async (resolve, reject) => {
        const animation =
          config.pos == null
            ? this.slideToObject(mobile, config.to || targetId, config.duration, config.delay)
            : this.slideToObjectPos(mobile, config.to || targetId, config.pos.x, config.pos.y, config.duration, config.delay);

        dojo.connect(animation, 'onEnd', () => {
          dojo.style(mobile, 'zIndex', null);
          dojo.removeClass(mobile, config.className);
          if (config.phantomStart) {
            dojo.place(mobileElt, mobile, 'replace');
            dojo.removeClass(mobileElt, 'phantom');
            mobile = mobileElt;
          }
          if (config.destroy) this.destroy(mobile);
          else if (config.changeParent) {
            if (config.phantomEnd) dojo.place(mobile, targetId, 'replace');
            else this.changeParent(mobile, newParent);
          }
          if (config.clearPos && !config.destroy) dojo.style(mobile, { top: null, left: null, position: null });
          resolve();
        });
        //animation.play();
        //await this.bgaPlayDojoAnimation(animation);
        this.bgaPlayDojoAnimation(animation);
      });
    },

    changeParent(mobile, new_parent, relation) {
      if (mobile === null) {
        console.error('attachToNewParent: mobile obj is null');
        return;
      }
      if (new_parent === null) {
        console.error('attachToNewParent: new_parent is null');
        return;
      }
      if (typeof mobile == 'string') {
        mobile = $(mobile);
      }
      if (typeof new_parent == 'string') {
        new_parent = $(new_parent);
      }
      if (typeof relation == 'undefined') {
        relation = 'last';
      }
      var src = this.getBoundingClientRectIgnoreZoom(mobile);
      dojo.style(mobile, 'position', 'absolute');
      dojo.place(mobile, new_parent, relation);
      var tgt = this.getBoundingClientRectIgnoreZoom(mobile);
      var box = dojo.marginBox(mobile);
      var cbox = dojo.contentBox(mobile);
      var left = box.l + src.x - tgt.x;
      var top = box.t + src.y - tgt.y;
      this.positionObjectDirectly(mobile, left, top);
      box.l += box.w - cbox.w;
      box.t += box.h - cbox.h;
      return box;
    },

    positionObjectDirectly(mobileObj, x, y) {
      // do not remove this "dead" code some-how it makes difference
      dojo.style(mobileObj, 'left'); // bug? re-compute style
      // console.log("place " + x + "," + y);
      dojo.style(mobileObj, {
        left: x + 'px',
        top: y + 'px',
      });
      dojo.style(mobileObj, 'left'); // bug? re-compute style
    },

    /*
     * Wrap a node inside a flip container to trigger a flip animation before replacing with another node
     */
    flipAndReplace: async function(target, newNode, duration = 1000) {
      debug("flipAndReplace start");
      if (!this.bgaAnimationsActive()) {
        dojo.place(newNode, target, 'replace');
        return;
      }

      return new Promise((resolve, reject) => {
        // Wrap everything inside a flip container
        let container = dojo.place(
          `<div class="flip-container flipped">
            <div class="flip-inner">
              <div class="flip-front"></div>
              <div class="flip-back"></div>
            </div>
          </div>`,
          target,
          'after'
        );
        dojo.place(target, container.querySelector('.flip-back'));
        dojo.place(newNode, container.querySelector('.flip-front'));

        // Trigget flip animation
        container.offsetWidth;
        dojo.removeClass(container, 'flipped');

        // Clean everything once it's done
        setTimeout(() => {
          dojo.place(newNode, container, 'replace');
          resolve();
        }, duration);
        debug("flipAndReplace end");
      });
    },

    /*
     * Return a span with a colored 'You'
     */
    coloredYou() {
      var color = this.gamedatas.players[this.player_id].color;
      var color_bg = '';
      if (this.gamedatas.players[this.player_id] && this.gamedatas.players[this.player_id].color_back) {
        color_bg = 'background-color:#' + this.gamedatas.players[this.player_id].color_back + ';';
      }
      var you = '<span style="font-weight:bold;color:#' + color + ';' + color_bg + '">' + __('lang_mainsite', 'You') + '</span>';
      return you;
    },

    coloredPlayerName(name, specifiedColor = null) {
      //debug("coloredPlayerName",name, specifiedColor);
      const player = Object.values(this.gamedatas.players).find((player) => player.name == name);
      if (player == undefined) return `<!--PNS--><span class="playername playername_wrapper_${specifiedColor}">${name}</span><!--PNE-->`;

      const color = specifiedColor ? specifiedColor : player.color;
      const color_bg = player.color_back ? 'background-color:#' + player.color_back + ';' : '';
      return `<!--PNS--><span class="playername playername_wrapper_${color}" style="color:#${color};${color_bg}">${name}</span><!--PNE-->`;
    },
    
    getPlayerColor(pId) {
      return this.gamedatas.players[pId].color;
    },

    refreshPlayersDatas(players){
        debug("refreshPlayersDatas()",players);
        //Erasing this array would erase some BGA datas (ex color_back)-> we should erase only datas in parameter array
        //this.gamedatas.players = players;
        Object.values(players).forEach((player) => {
            let pid = player.id;
            //Object.keys(player).forEach((data) => {
            //    this.gamedatas.players[pid][data] = player[data];
            //});
            for (const property in player) {
                this.gamedatas.players[pid][property] = player[property];
            }
        });
    },
    /*
     * Overwrite to allow to more player coloration than player_name and player_name2
     */
    format_string_recursive(log, args) {
      try {
        if (log && args) {
          //          if (args.msgYou && args.player_id == this.player_id) log = args.msgYou;
          
          let playernames_keys = Object.keys(args).filter((key) => key.startsWith( 'player_name'));
          let color_keys = Object.keys(args).filter((key) => key.startsWith('player_color'));
          for(let k=0; k<playernames_keys.length; k++){
            let key = playernames_keys[k];
            let colorKey = color_keys[k];
            args[key] = this.coloredPlayerName(args[key],args[colorKey]);
          }

          //          args.You = this.coloredYou();
        }
      } catch (e) {
        console.error(log, args, 'Exception thrown', e.stack);
      }

      return this.inherited(arguments);
    },

    place(tplMethodName, object, container, position = null) {
      if ($(container) == null) {
        console.error('Trying to place on null container', container, tplMethodName, object);
        return;
      }

      if (this[tplMethodName] == undefined) {
        console.error('Trying to create a non-existing template', tplMethodName);
        return;
      }

      return dojo.place(this[tplMethodName](object), container, position);
    },

    /* Helper to work with local storage */
    getConfig(value, v) {
      return localStorage.getItem(value) == null || isNaN(localStorage.getItem(value)) ? v : localStorage.getItem(value);
    },

    /**********************
     ****** HELP MODE ******
     **********************/
    /**
     * Toggle help mode
     */
    toggleHelpMode(b) {
      if (b) this.activateHelpMode();
      else this.desactivateHelpMode();
    },

    activateHelpMode() {
      this._helpMode = true;
      dojo.addClass('ebd-body', 'help-mode');
      this._displayedTooltip = null;
      document.body.addEventListener('click', this.closeCurrentTooltip.bind(this));
    },

    desactivateHelpMode() {
      this.closeCurrentTooltip();
      this._helpMode = false;
      dojo.removeClass('ebd-body', 'help-mode');
      document.body.removeEventListener('click', this.closeCurrentTooltip.bind(this));
    },

    closeCurrentTooltip() {
      if (!this._helpMode) return;

      if (this._displayedTooltip == null) return;
      else {
        this._displayedTooltip.close();
        this._displayedTooltip = null;
      }
    },

    /*
     * Custom connect that keep track of all the connections
     *  and wrap clicks to make it work with help mode
     */
    connect(node, action, callback) {
      this._connections.push(dojo.connect($(node), action, callback));
    },

    onClick(node, callback, temporary = true) {
      let safeCallback = (evt) => {
        evt.stopPropagation();
        if (this.isInterfaceLocked()) return false;
        if (this._helpMode) return false;
        callback(evt);
      };

      if (temporary) {
        this.connect($(node), 'click', safeCallback);
        dojo.removeClass(node, 'unselectable');
        dojo.addClass(node, 'selectable');
        this._selectableNodes.push(node);
      } else {
        dojo.connect($(node), 'click', safeCallback);
      }
    },

    /**
     * Tooltip to work with help mode
     */
    registerCustomTooltip(html, id = null) {
      id = id || this.game_name + '-tooltipable-' + this._customTooltipIdCounter++;
      this._registeredCustomTooltips[id] = html;
      return id;
    },
    attachRegisteredTooltips() {
      Object.keys(this._registeredCustomTooltips).forEach((id) => {
        if (!$(id)) {
          console.error('Trying to attack tooltip on a null element', id);
        } else {
          this.addCustomTooltip(id, this._registeredCustomTooltips[id]);
        }
      });
      this._registeredCustomTooltips = {};
    },
    addCustomTooltip(id, html, delay) {
      if (this.tooltips[id]) {
        this.tooltips[id].label = html;
        return;
      }

      html = '<div class="midSizeDialog">' + html + '</div>';
      delay = delay || 400;
      let tooltip = new dijit.Tooltip({
        //        connectId: [id],
        label: html,
        position: this.defaultTooltipPosition,
        showDelay: delay,
      });
      this.tooltips[id] = tooltip;
      dojo.addClass(id, 'tooltipable');
      dojo.place(
        //<div class='help-marker-background' id='help-marker-background-${id}'></div>
        `
        <div class='help-marker' id='help-marker-${id}'>
          <svg><use href="#help-marker-svg" /></svg>
        </div>
      `,
        id
      );

      //
      let divToApply = $(`${id}`);
      if($("ebd-body").classList.contains("touch-device")){
        //TO avoid interference on touch mobile
        divToApply = $(`help-marker-${id}`);
      }


      dojo.connect($(`${id}`), 'click', (evt) => {
        if (!this._helpMode || this.bHideTooltips) {
          tooltip.close();
        } else {
          evt.stopPropagation();

          if (tooltip.state == 'SHOWING') {
            this.closeCurrentTooltip();
          } else {
            this.closeCurrentTooltip();
            tooltip.open($(id));
            this.reduceTextSizeOnCardElements($("dijit__MasterTooltip_0"));
            this._displayedTooltip = tooltip;
          }
        }
      });

      tooltip.showTimeout = null;
      dojo.connect(divToApply, 'mouseenter', () => {
        //BGA preference Disabled
        if(this.bHideTooltips) return;

        if (!this._helpMode && !this._dragndropMode) {
          if (tooltip.showTimeout != null) clearTimeout(tooltip.showTimeout);

          tooltip.showTimeout = setTimeout(() => {
              tooltip.open($(id)); 
              this.reduceTextSizeOnCardElements($("dijit__MasterTooltip_0"));
            }, 
            delay);
        }
      });

      dojo.connect(divToApply, 'mouseleave', () => {
        if (!this._helpMode && !this._dragndropMode) {
          tooltip.close();
          if (tooltip.showTimeout != null) clearTimeout(tooltip.showTimeout);
        }
      });
    },


    /*
     * cancelLogs:
     *   strikes all log messages related to the given array of notif ids
     */
    checkLogCancel(notifId) {
      if (this.gamedatas.canceledNotifIds != null && this.gamedatas.canceledNotifIds.includes(notifId)) {
        this.cancelLogs([notifId]);
      }
    },

    /*
     * [Undocumented] Called by BGA framework on any notification message
     * Handle cancelling log messages for restart turn
     */
    onPlaceLogOnChannel(msg) {
      var currentLogId = this.notifqueue.next_log_id;
      var currentMobileLogId = this.next_log_id;
      var res = this.inherited(arguments);
      this._notif_uid_to_log_id[msg.uid] = currentLogId;
      this._notif_uid_to_mobile_log_id[msg.uid] = currentMobileLogId;
      this._last_notif = {
        logId: currentLogId,
        mobileLogId: currentMobileLogId,
        msg,
      };
      return res;
    },

    cancelLogs(notifIds) {
      notifIds.forEach((uid) => {
        if (this._notif_uid_to_log_id.hasOwnProperty(uid)) {
          let logId = this._notif_uid_to_log_id[uid];
          if ($('log_' + logId)) dojo.addClass('log_' + logId, 'cancel');
        }
        if (this._notif_uid_to_mobile_log_id.hasOwnProperty(uid)) {
          let mobileLogId = this._notif_uid_to_mobile_log_id[uid];
          if ($('dockedlog_' + mobileLogId)) dojo.addClass('dockedlog_' + mobileLogId, 'cancel');
        }
      });
    },

    addLogClass() {
      if (this._last_notif == null) return;

      let notif = this._last_notif;
      let type = notif.msg.type;
      if (type == 'history_history') type = notif.msg.args.originalType;

      if ($('log_' + notif.logId)) {
        dojo.addClass('log_' + notif.logId, 'notif_' + type);

        var methodName = 'onAdding' + type.charAt(0).toUpperCase() + type.slice(1) + 'ToLog';
        if (this[methodName] !== undefined) this[methodName](notif);
      }
      if ($('dockedlog_' + notif.mobileLogId)) {
        dojo.addClass('dockedlog_' + notif.mobileLogId, 'notif_' + type);
      }
    },

    /**
     * Own counter implementation that works with replay
     */
    createCounter(id, defaultValue = 0, linked = null) {
      if (!$(id)) {
        console.error('Counter : element does not exist', id);
        return null;
      }

      let game = this;
      let o = {
        span: $(id),
        linked: linked ? $(linked) : null,
        targetValue: 0,
        currentValue: 0,
        speed: 100,
        getValue() {
          return this.targetValue;
        },
        setValue(n) {
          this.currentValue = +n;
          this.targetValue = +n;
          this.span.innerHTML = +n;
          this.span.dataset.counter = +n;
          if(this.currentValue==0) this.span.parentNode.classList.add("counter_empty");
          else this.span.parentNode.classList.remove("counter_empty");
          if (this.linked) this.linked.innerHTML = +n;
        },
        toValue(n) {
          if (!game.bgaAnimationsActive()) {
            this.setValue(n);
            return;
          }

          this.targetValue = +n;
          if (this.currentValue != n) {
            this.span.classList.add('counter_in_progress');
            setTimeout(() => this.makeCounterProgress(), this.speed);
          }
        },
        goTo(n, anim) {
          if (anim) this.toValue(n);
          else this.setValue(n);
        },
        incValue(n) {
          let m = +n;
          this.toValue(this.targetValue + m);
        },
        makeCounterProgress() {
          if (this.currentValue == this.targetValue) {
            setTimeout(() => this.span.classList.remove('counter_in_progress'), this.speed);
            return;
          }

          let step = Math.ceil(Math.abs(this.targetValue - this.currentValue) / 5);
          this.currentValue += (this.currentValue < this.targetValue ? 1 : -1) * step;
          this.span.innerHTML = this.currentValue;
          this.span.dataset.counter = this.currentValue;
          if(this.currentValue==0) this.span.parentNode.classList.add("counter_empty");
          else this.span.parentNode.classList.remove("counter_empty");
          if (this.linked) this.linked.innerHTML = this.currentValue;
          setTimeout(() => this.makeCounterProgress(), this.speed);
        },
      };
      o.setValue(defaultValue);
      return o;
    },

    /****************
     ***** UTILS *****
     ****************/
    forEachPlayer(callback) {
      Object.values(this.gamedatas.players).forEach(callback);
    },

    getArgs() {
      return this.gamedatas.gamestate.args;
    },

    clientState(name, descriptionmyturn, args) {
      this.setClientState(name, {
        descriptionmyturn,
        args,
      });
    },

    strReplace(str, subst) {
      return dojo.string.substitute(str, subst);
    },

    addCancelStateBtn(text = null) {
      if (text == null) {
        text = _('Cancel');
      }

      this.addSecondaryActionButton('btnCancel', text, () => this.clearClientState());
    },

    clearClientState() {
      //this.clearPossible();
      this.restoreServerGameState();
    },

    translate(t) {
      if (typeof t === 'object') {
        return this.format_string_recursive(t.log, t.args);
      } else {
        return _(t);
      }
    },

    fsr(log, args) {
      return this.format_string_recursive(log, args);
    },

    /** Make the token blink 2 times */
    animationBlink2Times(divId){
      let anim = dojo.fx.chain( [
          dojo.fadeOut( { node: divId } ),
          dojo.fadeIn( { node: divId } ),
          dojo.fadeOut( { node: divId } ),
          dojo.fadeIn( { node: divId  } )
      ] );
      anim.play();
    },
    /** Useful When we want to select mutliple elements at the same time.
     * 
     * Optional parameter "listPossibleSets" will make selection impossible if not in list of sets/arrays.
     * Optional parameter "autoConfirm" will make selection callback as soon as we have a possible set
    */
    onSelectN(elements, n, callbackOnSelectionEnd, listPossibleSets =null, autoConfirm = false) {
      let selectedElements = [];
      let endSelection = () => {
        if (callbackOnSelectionEnd(selectedElements)) {
          selectedElements = [];
          updateStatus();
        }
      }
      let updateStatus = () => {
        if ($('btnConfirmChoice')) $('btnConfirmChoice').remove();
        if (selectedElements.length == n) {
          this.addPrimaryActionButton('btnConfirmChoice', _('Confirm'), () => {
            endSelection();
          });
        }

        if ($('btnCancelChoice')) $('btnCancelChoice').remove();
        if (selectedElements.length  > 0) {
          this.addSecondaryActionButton('btnCancelChoice', _('Cancel selection'), () => {
            selectedElements = [];
            updateStatus();
          });
        }

        Object.keys(elements).forEach((id) => {
          let elt = elements[id];
          let selected = selectedElements.includes(parseInt(id));
          elt.classList.toggle('selected', selected);
          elt.classList.toggle('selectable', selected || selectedElements.length < n);
        });
        
      };
      let checkPossibleSets = () => {

        if(listPossibleSets!=null){
          let includesAll = (arr, target) => target.every(v => arr.includes(v));
          //Check each element not currently selected
          Object.keys(elements).forEach((id2) => {
            let elt2 = elements[id2];
            let selected2 = selectedElements.includes(parseInt(id2));
            if(selected2) return;
            let tmp = Array.from(selectedElements);
            tmp.push(parseInt(id2));
            let possibleSet = false;
            Object.values(listPossibleSets).forEach((pSet) => {
              if( includesAll(pSet,tmp)){
                possibleSet = true;
              }
            });
            
            elt2.classList.toggle('selectable', selected2 || possibleSet && selectedElements.length < n);
            
          });

          let nbSelectables = 0;
          Object.keys(elements).forEach((id) => {
            let elt = elements[id];
            if(elt.classList.contains("selectable")) nbSelectables++;
          });
          if(nbSelectables == n){
            //If only 1 match in fixed possible sets, let's auto select it
            debug("onSelectN... auto select ",nbSelectables);
            Object.keys(elements).forEach((id) => {
              let elt = elements[id];
              if(elt.classList.contains("selectable")){
                elt.classList.add("selected");
                if(!selectedElements.includes(parseInt(id))) selectedElements.push(parseInt(id));
              }
            });
            updateStatus();
            if(autoConfirm) endSelection();

          }
        }
      };

      Object.keys(elements).forEach((id) => {
        let elt = elements[id];

        this.onClick(elt, () => {
          if(!elt.classList.contains("selectable")) return;

          let index = selectedElements.findIndex((t) => t == id);

          if (index === -1) {
            if (selectedElements.length >= n) return;
            selectedElements.push(parseInt(id));
          } else {
            selectedElements.splice(index, 1);
          }
          updateStatus();
          checkPossibleSets();
        });
      });
    },

    //TODO JSA test new BGA Component "bga-autofit" instead
    //Taken from thoun Ancient Knowledge : reduce a div text size to match a specific zone (on a card for example)
    //EXAMPLE <span class='A'><div class='reduceToFit'>TEST abcdef</div></span> where .A elements define a width
    reduceToFit(element) {
      var div = element; //element.getElementsByTagName("div")[0];
      if (div) {
        var n = window.getComputedStyle(div).fontSize.match(/\d+/);
        if (n)
          for (var a = Number(n[0]); div.clientHeight > element.parentNode.clientHeight && a > 5;) {
            a--;
            div.style.fontSize = "".concat(a, "px")
          }
      }
    },
    reduceTextSizeOnCardElements(cardDiv) {
      if(!cardDiv) return;
      cardDiv.querySelectorAll(".reduceToFit").forEach((e) => {
        this.reduceToFit(e);
      });
    },

    /**
     * 
     * idea from bennygui (see Earth) to keep a fixed page title even with many buttons
     */
    adaptStatusBar() {
      //debug("adaptStatusBar");
      this.inherited(arguments);

      if (this.alwaysFixTopActions) {
        const afterTitleElem = document.getElementById('after-page-title');
        const titleElem = document.getElementById('page-title');
        let zoom = getComputedStyle(titleElem).zoom;
        if (!zoom) {
          zoom = 1;
        }

        const titleRect = afterTitleElem.getBoundingClientRect();
        if (titleRect.top < 0 && (titleElem.offsetHeight < (window.innerHeight * this.alwaysFixTopActionsMaximum / 100))) {
          const afterTitleRect = afterTitleElem.getBoundingClientRect();
          titleElem.classList.add('fixed-page-title');
          titleElem.style.width = ((afterTitleRect.width - 10) / zoom) + 'px';
          afterTitleElem.style.height = titleRect.height + 'px';
        } else {
          titleElem.classList.remove('fixed-page-title');
          titleElem.style.width = 'auto';
          afterTitleElem.style.height = '0px';
        }
      }
    },

  });
});

//FOR STUDIO ONLY //# sourceURL=game.js