jpf.onload = function() {
    var session_token = jpf.getcookie("session_token");
    
    if (session_token) {
        comm.info();
        //M_WINmain.show();
    }
    else {
        comm.attachurl();
    }
}

jpf.auth.onloginsuccess = function(data) {
    comm.info();
    M_WINinfo.show();
    M_WINmain.hide();
}

jpf.auth.onlogoutsuccess = function(data) {
    M_WINinfo.hide();
    M_WINmain.show();
}

jpf.auth.onlogoutfail = function(data) {

}
jpf.auth.onloginfail = function(data) {
   
}

function M_afterAttachURL(data, state, extra) {
    if (state == jpf.SUCCESS) {
        img = new Image();

        document.body.appendChild(img);
        
        img.src = data;
        
        img.onerror = function(e) {
            alert("Image loading error");
            jpf.flow.alert_r(e);
        }
        
        img.onabort = function() {
            alert("Image loading aborted");
        }
        
        img.onload = function() {
            jpf.console.info("Image has been loaded");
            comm.info();
        }
    }
    else {
        alert("An error occur: " + extra.message);
    }
}

function M_afterInfo(data, state, extra) {
    if (state == jpf.SUCCESS) {
        lblLoggedIn.setValue("You are logged in as " + jpf.getXmlValue(jpf.getXml(data), "fullname"));

        M_WINmain.hide();
        M_WINinfo.show();
    }
    else {
        M_WINmain.show();
    }
}
