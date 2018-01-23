vcl 4.0;

# Default backend definition. Points to Apache, normally.
backend default {
    .host = "{{ getenv "VARNISH_BACKEND_HOST" }}";
    .port = "{{ getenv "VARNISH_BACKEND_PORT" "80" }}";
    .first_byte_timeout     = {{ getenv "VARNISH_BACKEND_FIRST_BYTE_TIMEOUT" "300s" }};   # How long to wait before we receive a first byte from our backend?
    .connect_timeout        = {{ getenv "VARNISH_BACKEND_CONNECT_TIMEOUT" "5s" }};     # How long to wait for a backend connection?
    .between_bytes_timeout  = {{ getenv "VARNISH_BACKEND_BETWEEN_BYTES_TIMEOUT" "2s" }};     # How long to wait between bytes received from our backend?
}

{{ $static_files := (getenv "VARNISH_STATIC_FILES" "pdf|asc|dat|txt|doc|xls|ppt|tgz|csv|png|gif|jpeg|jpg|ico|swf|css|js|svg") }}

{{ $exclude_urls := (getenv "VARNISH_EXCLUDE_URLS" "^(/update\\.php|/([a-z]{2}/)?admin|/([a-z]{2}/)?admin/.*|/([a-z]{2}/)?system/files/.*|/([a-z]{2}/)?flag/.*|.*/ajax/.*|.*/ahah/.*)$") }}

# Respond to incoming requests.
sub vcl_recv {

    # Protecting against the HTTPOXY CGI vulnerability.
    unset req.http.proxy;

    if (req.method == "PURGE") {
        {{ if not (getenv "VARNISH_ALLOW_UNRESTRICTED_PURGE") }}
        # Allow PURGE requests from internal network only.
        if (req.http.X-Real-IP) {
            return (synth(405, "Not allowed."));
        }
        {{ end }}
        return (hash);
    }

    if (req.method == "BAN") {
        {{ if not (getenv "VARNISH_ALLOW_UNRESTRICTED_BAN") }}
        # Allow BAN requests from internal network only.
        if (req.http.X-Real-IP) {
            return (synth(403, "Not allowed."));
        }
        {{ end }}
        # Logic for the ban, using the Purge-Cache-Tags header. For more info
        if (req.http.Purge-Cache-Tags) {
            ban("obj.http.Purge-Cache-Tags ~ " + req.http.Purge-Cache-Tags);
        }
        else {
            return (synth(403, "Purge-Cache-Tags header missing."));
        }

        # Throw a synthetic page so the request won't go to the backend.
        return (synth(200, "Ban added."));
    }

    # Only cache GET and HEAD requests (pass through POST requests).
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Pass through any administrative or AJAX-related paths.
    if (req.url ~ "{{ $exclude_urls }}") {
           return (pass);
    }

    # Implementing websocket support (https://www.varnish-cache.org/docs/4.0/users-guide/vcl-example-websockets.html)
    if (req.http.Upgrade ~ "(?i)websocket") {
        return (pipe);
    }

    # Some generic URL manipulation, useful for all templates that follow
    # First remove the Google Analytics added parameters, useless for our backend
    if (req.url ~ "(\?|&)(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=") {
        set req.url = regsuball(req.url, "&(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "");
        set req.url = regsuball(req.url, "\?(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "?");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }

    # Strip hash, server doesn't need it.
    if (req.url ~ "\#") {
        set req.url = regsub(req.url, "\#.*$", "");
    }

    # Strip a trailing ? if it exists
    if (req.url ~ "\?$") {
        set req.url = regsub(req.url, "\?$", "");
    }

    # Removing cookies for static content so Varnish caches these files.
    if (req.url ~ "(?i)\.({{ $static_files }})(\?.*)?$") {
        unset req.http.Cookie;
    }

    # Remove all cookies that Drupal doesn't need to know about. We explicitly
    # list the ones that Drupal does need, the SESS and NO_CACHE. If, after
    # running this code we find that either of these two cookies remains, we
    # will pass as the page cannot be cached.
    if (req.http.Cookie) {
        # 1. Append a semi-colon to the front of the cookie string.
        # 2. Remove all spaces that appear after semi-colons.
        # 3. Match the cookies we want to keep, adding the space we removed
        #    previously back. (\1) is first matching group in the regsuball.
        # 4. Remove all other cookies, identifying them by the fact that they have
        #    no space after the preceding semi-colon.
        # 5. Remove all spaces and semi-colons from the beginning and end of the
        #    cookie string.
        set req.http.Cookie = ";" + req.http.Cookie;
        set req.http.Cookie = regsuball(req.http.Cookie, "; +", ";");
        set req.http.Cookie = regsuball(req.http.Cookie, ";(SESS[a-z0-9]+|SSESS[a-z0-9]+|NO_CACHE)=", "; \1=");
        set req.http.Cookie = regsuball(req.http.Cookie, ";[^ ][^;]*", "");
        set req.http.Cookie = regsuball(req.http.Cookie, "^[; ]+|[; ]+$", "");

        if (req.http.Cookie == "") {
            # If there are no remaining cookies, remove the cookie header. If there
            # aren't any cookie headers, Varnish's default behavior will be to cache
            # the page.
            unset req.http.Cookie;
        }
        else {
            # If there is any cookies left (a session or NO_CACHE cookie), do not
            # cache the page. Pass it on to Apache directly.
            return (pass);
        }
    }
}

sub vcl_hash {
    hash_data(req.url);

    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }

    # Use special internal SSL hash for https content
    # X-Forwarded-Proto is set to https by Pound
    if (req.http.X-Forwarded-Proto ~ "https") {
        hash_data(req.http.X-Forwarded-Proto);
    }

    return (lookup);
}

# Set a header to track a cache HITs and MISSes.
sub vcl_deliver {
    # Remove ban-lurker friendly custom headers when delivering to client.
    unset resp.http.X-Url;
    unset resp.http.X-Host;
    unset resp.http.Purge-Cache-Tags;
    unset resp.http.Cache-Tags;
    unset resp.http.X-Drupal-Cache-Contexts;
    unset resp.http.X-Drupal-Cache-Tags;

    # Remove some headers
    unset resp.http.Via;

    if (obj.hits > 0) {
        set resp.http.X-Varnish-Cache = "HIT";
    }
    else {
        set resp.http.X-Varnish-Cache = "MISS";
    }
}

# Instruct Varnish what to do in the case of certain backend responses (beresp).
sub vcl_backend_response {
    # Set ban-lurker friendly custom headers.
    set beresp.http.X-Url = bereq.url;
    set beresp.http.X-Host = bereq.http.host;

    # Cache 404s, 301s, at 500s with a short lifetime to protect the backend.
    if (beresp.status == 404 || beresp.status == 301 || beresp.status == 500) {
        set beresp.ttl = {{ getenv "VARNISH_ERRORS_TTL" "10m" }};
    }

    # Enable streaming directly to backend for BigPipe responses.
    if (beresp.http.Surrogate-Control ~ "BigPipe/1.0") {
        set beresp.do_stream = true;
        set beresp.ttl = 0s;
    }

    # Don't allow static files to set cookies.
    # (?i) denotes case insensitive in PCRE (perl compatible regular expressions).
    # This list of extensions appears twice, once here and again in vcl_recv so
    # make sure you edit both and keep them equal.
    if (bereq.url ~ "(?i)\.({{ $static_files }})(\?.*)?$") {
        unset beresp.http.set-cookie;
    }

    # Allow items to remain in cache up to N hours past their cache expiration.
    set beresp.grace = {{ getenv "VARNISH_GRACE" "6h" }};
}

sub vcl_pipe {
    # Called upon entering pipe mode.
    # In this mode, the request is passed on to the backend, and any further data from both the client
    # and backend is passed on unaltered until either end closes the connection. Basically, Varnish will
    # degrade into a simple TCP proxy, shuffling bytes back and forth. For a connection in pipe mode,
    # no other VCL subroutine will ever get called after vcl_pipe.

    # Note that only the first request to the backend will have
    # X-Forwarded-For set.  If you use X-Forwarded-For and want to
    # have it set for all requests, make sure to have:
    # set bereq.http.connection = "close";
    # here.  It is not set by default as it might break some broken web
    # applications, like IIS with NTLM authentication.

    # set bereq.http.Connection = "Close";

    # Implementing websocket support (https://www.varnish-cache.org/docs/4.0/users-guide/vcl-example-websockets.html)
    if (req.http.upgrade) {
        set bereq.http.upgrade = req.http.upgrade;
    }

    return (pipe);
}

# In the event of an error, show friendlier messages.
sub vcl_backend_error {
  set beresp.http.Content-Type = "text/html; charset=utf-8";
  synthetic ({"
<html>
<head>
    <title>Not Found</title>
    <style type="text/css">
        A:link {text-decoration: none;  color: #333333;}
        A:visited {text-decoration: none; color: #333333;}
        A:active {text-decoration: none}
        A:hover {text-decoration: underline;}
    </style>
</head>
<body onload="setTimeout(function() { window.location.reload() }, 5000)" bgcolor=white text=#333333 style="padding:5px 15px 5px 15px; font-family: myriad-pro-1,myriad-pro-2,corbel,sans-serif;">
<H3>Page Unavailable</H3>
<p>The page you requested is temporarily unavailable.</p>
</body>
</html>
"});
  return (deliver);
}
