var distToBottom, data, dataObj;
var page = 1;
var pollingForData = false;
var xhr = new XMLHttpRequest();
var contentContainer = document.getElementsByClassName('logs')[0];
var loadingContainer = document.getElementsByClassName('loading-container')[0];
var player_page_title = document.querySelector('.player-name');

if(player_page_title != null){
  var player_name = player_page_title.textContent;
}

function getDistFromBottom () {
  
  var scrollPosition = window.pageYOffset;
  var windowSize     = window.innerHeight;
  var bodyHeight     = document.body.offsetHeight;

  return Math.max(bodyHeight - (scrollPosition + windowSize), 0);

}

xhr.onload = function() {
  if(xhr.status === 200) {

    pollingForData = false;
    data = xhr.responseText
    dataObj = JSON.parse(data);
    
    // for iterating through the data
    // Using a ForEach
    dataObj.forEach(function(log, index) {

      var logItem = document.createElement('li');
          logItem.classList.add('log-item');

      var logHeader = document.createElement('div');
          logHeader.classList.add('log-header');
      
      var logTitle = document.createElement('h2');
          logTitle.appendChild(document.createTextNode(log.lg_title));
          logTitle.classList.add('log-title');

      var logTimestamp = document.createElement('span');
          logTimestamp.appendChild(document.createTextNode(moment.unix(log.lg_ts).format("MMMM D, YYYY h:mm")));
          logTimestamp.classList.add('log-timestamp');
      
      var logDetails = document.createElement('p');
          logDetails.appendChild(document.createTextNode(log.lg_details));
          logDetails.classList.add('log-details');

      logItem.appendChild(logHeader);
      logHeader.appendChild(logTitle);
      logHeader.appendChild(logTimestamp);
      logItem.appendChild(logDetails);
      
      contentContainer.appendChild(logItem);
    })
  }
};

document.addEventListener('scroll', function() {
  distToBottom = getDistFromBottom();

  if (!pollingForData && distToBottom > 0 && distToBottom <= 200) {
    pollingForData = true;
    loadingContainer.classList.add('no-content');

    page++;
    xhr.open('GET', 'https://runelo.gs/load/'+player_name+'/'+page, true);
    xhr.send();
  }
});

/*
Nightmode toggle (global)
*/
var body = document.querySelector('body');
var is_nightmode = body.classList.contains('is-nightmode');
var night_mode_toggle = document.querySelector('.night-mode-toggle');
var night_mode_icon = night_mode_toggle.querySelector(':scope .fas');

function toggle_night_mode(evt = false) {
  if (evt) evt.preventDefault();

  body.classList.toggle('is-nightmode');
  is_nightmode = !is_nightmode;
  night_mode_icon.className = is_nightmode == true ? 'fas fa-sun' : 'fas fa-moon';
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