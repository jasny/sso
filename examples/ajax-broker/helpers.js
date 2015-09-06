function microAjax(B,A){this.bindFunction=function(E,D){return function(){return E.apply(D,[D]);};};this.stateChange=function(D){if(this.request.readyState==4){this.callbackFunction(this.request.responseText);}};this.getRequest=function(){if(window.ActiveXObject){return new ActiveXObject("Microsoft.XMLHTTP");}else{if(window.XMLHttpRequest){return new XMLHttpRequest();}}return false;};this.postBody=(arguments[2]||"");this.callbackFunction=A;this.url=B;this.request=this.getRequest();if(this.request){var C=this.request;C.onreadystatechange=this.bindFunction(this.stateChange,this);if(this.postBody!==""){C.open("POST",B,true);C.setRequestHeader("X-Requested-With","XMLHttpRequest");C.setRequestHeader("Content-type","application/x-www-form-urlencoded");C.setRequestHeader("Connection","close");}else{C.open("GET",B,true);}C.send(this.postBody);}};

var token;

function attachSession() {
  microAjax('/examples/ajax-broker/ajax.php?command=attach&token='+ token, function(data) {
    console.log(data);
  });
}

function getToken(f) {
  microAjax('/examples/ajax-broker/ajax.php?command=getToken', function(data) {
    token = data;
    console.log('token is ready');
  });
}

function login() {
  var username = document.querySelector('input[name="username"]').value;
  var password = document.querySelector('input[name="password"]').value;
  var query = [
    'command=login',
    'username='+username,
    'password='+password,
    'token='+token
  ];

  microAjax('/examples/ajax-broker/ajax.php?' + query.join('&'), function(data) {
    console.log(data);
    var outputDiv = document.querySelector('#output');
    var output = "";
    var jsonData = JSON.parse(data);

    for (var key in jsonData) {
      output += key + ": " + jsonData[key] + "<br>";
    }
    outputDiv.innerHTML = output;
  });
}

getToken();
