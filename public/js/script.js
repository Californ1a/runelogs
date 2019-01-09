var distToBottom, data, dataObj;
var page = 1;
var pollingForData = false;
var xhr = new XMLHttpRequest();
var contentContainer = document.getElementsByClassName('logs')[0];
var loadingContainer = document.getElementsByClassName('loading-container')[0];
var player_name = document.querySelector('.player-name').textContent;

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
    
    // removing the spinner
    //loadingContainer.classList.remove('no-content');
  }
};

//xhr.open('GET', 'http://localhost:8080/load/'+player_name+'/1', true);
//xhr.send();
//pollingForData = true;

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
