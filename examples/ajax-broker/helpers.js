function microAjax(B,A) {this.bindFunction=function (E,D) {return function () {return E.apply(D,[D]);};};this.stateChange=function (D) {if (this.request.readyState==4) {this.callbackFunction(this.request.responseText);}};this.getRequest=function () {if (window.ActiveXObject) {return new ActiveXObject("Microsoft.XMLHTTP");} else {if (window.XMLHttpRequest) {return new XMLHttpRequest();}}return false;};this.postBody=(arguments[2]||"");this.callbackFunction=A;this.url=B;this.request=this.getRequest();if (this.request) {var C=this.request;C.onreadystatechange=this.bindFunction(this.stateChange,this);if (this.postBody!=="") {C.open("POST",B,true);C.setRequestHeader("X-Requested-With","XMLHttpRequest");C.setRequestHeader("Content-type","application/x-www-form-urlencoded");C.setRequestHeader("Connection","close");} else {C.open("GET",B,true);}C.send(this.postBody);}};

var token = '';

function makeRequest(command, token, callback, postBody) {
  var url = '/examples/ajax-broker/ajax.php?command=' + encodeURIComponent(command);

  microAjax(url, callback, postBody);
}

function getToken() {
  makeRequest('getToken', '', function (data) {
    token = JSON.parse(data);
    console.log('token is ready:', token);
  });

  var buttons = document.querySelectorAll('button');
  console.log(buttons);
  for (var i = 0; i < buttons.length; i++) {
    buttons[i].disabled = false;
  }
}

function doRequest(command, callback, postbody) {
  makeRequest(command, token, function(data) {
    var outputDiv = document.querySelector('#output');
    outputDiv.innerHTML = data;
    callback(data);
  }, postbody || '');
}

function print() {
  console.log(arguments);
}

function login() {
  var username = document.querySelector('input[name="username"]').value;
  var password = document.querySelector('input[name="password"]').value;
  var query = [
    'username='+ username,
    'password='+ password
  ];

  doRequest('login', function(data){console.log(data);}, query.join('&'));
}

function attach() {
  doRequest('ajaxAttach', function(data){console.log(data);});
}

function detach() {
  doRequest('detach', function(data){console.log(data);});
}

function getUserInfo() {
  doRequest('getUserInfo', function(data){console.log(data);});
}
