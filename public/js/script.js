var data, dataObj;
var xhr = new XMLHttpRequest();
var content = document.querySelector('.content');
var log_container = document.getElementsByClassName('logs')[0];

let rsn = window.location.href.split("/")[3];
let player_name = (rsn) ? rsn : null;

/* Infinite scroll */

function display_logs(logs){

  log_container.innerHTML = '';
  var notification_container = document.querySelector('.notification');
  if(notification_container){
    notification_container.remove();
  }

  if (logs.length > 0) {
    logs.forEach(function(log, index) {

      var logItem = document.createElement('li');
      logItem.classList.add('log-item');

      var logHeader = document.createElement('div');
      logHeader.classList.add('log-header');

      var logTitle = document.createElement('h2');
      logTitle.appendChild(document.createTextNode(log.lg_title));
      logTitle.classList.add('log-title');

      var logTimestamp = document.createElement('span');
      logTimestamp.appendChild(document.createTextNode(moment.unix(log.lg_ts).format("MMMM D, YYYY hh:mm")));
      logTimestamp.classList.add('log-timestamp');

      var logDetails = document.createElement('p');
      logDetails.appendChild(document.createTextNode(log.lg_details));
      logDetails.classList.add('log-details');

      logItem.appendChild(logHeader);
      logHeader.appendChild(logTitle);
      logHeader.appendChild(logTimestamp);
      logItem.appendChild(logDetails);

      log_container.appendChild(logItem);
    })
  } else {

    var notification = document.createElement('section');
    notification.classList.add('text', 'notification', 'inf');

    var notificationTitle = document.createElement('h2');
    notificationTitle.appendChild(document.createTextNode("Nothing interesting happened."));

    var notificationText = document.createElement('p');
    notificationText.appendChild(document.createTextNode("Looks like you don't have any logs for this day."));

    notification.appendChild(notificationTitle);
    notification.appendChild(notificationText);

    content.insertBefore(notification, log_container);
  }
  
};

/* Nightmode toggle (global) */

var body = document.querySelector('body');
var is_nightmode = body.classList.contains('is-nightmode');
var night_mode_toggle = document.querySelector('.night-mode-toggle');
var night_mode_icon = night_mode_toggle.querySelector(':scope .fas');

function toggle_night_mode(evt = false) {
  if (evt) evt.preventDefault();

  body.classList.toggle('is-nightmode');
  is_nightmode = !is_nightmode;
  night_mode_toggle.classList.toggle('night');
  localStorage.setItem("nightmode", JSON.stringify(is_nightmode));
}

if(night_mode_toggle != null){
  night_mode_toggle.addEventListener('click', toggle_night_mode);
}

if (localStorage.getItem("nightmode") === null) {
  localStorage.setItem("nightmode", JSON.stringify(is_nightmode));
} else if (is_nightmode !== JSON.parse(localStorage.getItem("nightmode"))){
  toggle_night_mode();
}

/* Date clicker */

var grid_squares = document.querySelectorAll('.grid-square');
if (grid_squares != null) {
  for ( var i=0, len = grid_squares.length; i < len; i++ ) {
    grid_squares[i].addEventListener('click', function(e) {
      let square_date = e.target.dataset.date;
      var active = document.querySelectorAll('.today');
      active.forEach(function(square, index) {
        square.classList.toggle('today');
      })
      e.target.classList.toggle('today');
      if (square_date) {
        load_date(square_date);
      } else {
        load_date(0);
      }
    });
  }
}

function load_date(date){
  var data = {
    date: date,
    player_name: player_name
  };
  var xhr = new XMLHttpRequest();
  xhr.open('POST', '/api/get_events_by_date', true);
  xhr.setRequestHeader('Content-Type', 'application/json; charset=UTF-8');
  xhr.send(JSON.stringify(data));
  xhr.onreadystatechange = function() {
    if(xhr.readyState == 4 && xhr.status == 200) {
      display_logs(JSON.parse(xhr.responseText));
    }
  }
}

var home_input = document.querySelector('.home-input');
if(home_input){
  home_input.focus();
}

if (window.innerWidth < 500) {
  var mobile_grid = document.querySelector('.grid-container');
  var today = document.querySelector('.today');
  var today_index = Array.from(today.parentNode.children).indexOf(today);
  let column = Math.ceil(today_index / 7);
  mobile_grid.scrollLeft = (column * 22) - (mobile_grid.offsetWidth / 2 );
}