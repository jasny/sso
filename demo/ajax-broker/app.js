+function ($) {
    // Init
    attach();

    /**
     * Attach session.
     * Will redirect to SSO server.
     */
    function attach()
    {
        const req = $.ajax({
            url: 'attach.php',
            crossDomain: true,
            dataType: 'jsonp'
        });

        req.done(function (data, code) {
            if (code && code >= 400) { // jsonp failure
                showError(data);
                return;
            }

            loadUserInfo();
        });

        req.fail(function (jqxhr) {
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
    function doApiRequest(command, params, callback)
    {
        const req = $.ajax({
            url: 'api.php?command=' + command,
            method: params ? 'POST' : 'GET',
            data: params,
            dataType: 'json'
        });

        req.done(callback);

        req.fail(function (jqxhr) {
            showError(jqxhr.responseJSON || jqxhr.textResponse);
        });
    }

    /**
     * Display the error message
     *
     * @param data
     */
    function showError(data)
    {
        const message = typeof data === 'object' && data.error ? data.error : 'Unexpected error';
        $('#error').text(message).show();
    }

    /**
     * Load and display user info
     */
    function loadUserInfo()
    {
        doApiRequest('info', null, showUserInfo);
    }

    /**
     * Display user info
     *
     * @param info
     */
    function showUserInfo(info)
    {
        const body = $('body');
        const userInfo = $('#user-info');

        body.removeClass('anonymous authenticated');
        userInfo.html('');

        if (info) {
            for (var key in info) {
                userInfo.append($('<dt>').text(key));
                userInfo.append($('<dd>').text(info[key]));
            }
        }

        body.addClass(info ? 'authenticated' : 'anonymous');
    }

    /**
     * Submit login form through AJAX
     */
    $('#login-form').on('submit', function (e) {
        e.preventDefault();

        $('#error').text('').hide();

        var data = {
            username: this.username.value,
            password: this.password.value
        };

        doApiRequest('login', data, showUserInfo);
    });

    $('#logout').on('click', function () {
        doApiRequest('logout', {}, function () {
            showUserInfo(null);
        });
    })
}(jQuery);
