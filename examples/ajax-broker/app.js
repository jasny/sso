+function($) {
  // Init
  attach();
  
  /**
   * Attach session.
   * Will redirect to SSO server.
   */
  function attach() {
    var req = $.ajax({
      url: 'api.php?command=attach',
      dataType: 'jsonp'
    });
    
    req.done(function(data, code, error) {
      if (code && code >= 400) { // jsonp failure
        showError(error);
        return;
      }
      
      loadUserInfo();
    });
    
    req.fail(function(jqxhr) {
        showError(jqxhr.responseJSON || jqxhr.textResponse)
    });
  }
    
  /**
   * Do an AJAX request to the API
   * 
   * @param command   API command
   * @param params    POST data
   * @param callback  Callback function
   */
  function doApiRequest(command, params, callback) {
    var req = $.ajax({
      url: 'api.php?command=' + command,
      data: params,
      dataType: 'json'
    });

    req.done(callback);
    
    req.fail(function(jqxhr) {
      showError(jqxhr.responseJSON || jqxhr.textResponse);
    });
  }

  /**
   * Display the error message
   * 
   * @param data
   */
  function showError(data) {
    var message = typeof data === 'object' && data.error ? data.error : 'Unexpected error';
    $('#error').text(message).show();
  }

  /**
   * Load and display user info
   */
  function loadUserInfo() {
    doApiRequest('getUserinfo', null, showUserInfo);
  }
  
  /**
   * Display user info
   * 
   * @param info
   */
  function showUserInfo(info) {
    $('body').removeClass('anonymous, authenticated');
    $('#user-info').html('');
    
    if (info) {
      for (var key in info) {
        $('#user-info').append($('<dt>').text(key));
        $('#user-info').append($('<dd>').text(info[key]));
      }
    }
    
    $('body').addClass(info ? 'authenticated' : 'anonymous');
  }

  /**
   * Submit login form through AJAX
   */
  $('#login-form').on('submit', function(e) {
    e.preventDefault();
    
    $('#error').text('').show();
    
    var data = {
      username: $(this).find('input[name="username"]').value,
      password: $(this).find('input[name="password"]').value
    };

    doApiRequest('login', data, showUserInfo);
  });
  
  $('#logout').on('click', function() {
    doApiRequest('logout', null, function() { showUserInfo(null); });
  })
}(jQuery);
