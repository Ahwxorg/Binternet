// Binternet video loader. Nothing is fetched until the visitor presses the
// overlay play button: the video has no source, no HLS playlist is loaded and
// the native controls stay hidden. hls.js itself is loaded on the first play,
// and posters outside the viewport are fetched lazily.
(function () {
  "use strict";

  var hlsRequest = null;

  function ensureHls() {
    if (window.Hls) {
      return Promise.resolve(window.Hls);
    }
    if (hlsRequest) {
      return hlsRequest;
    }
    hlsRequest = new Promise(function (resolve, reject) {
      var script = document.createElement("script");
      script.src = "/static/js/hls.min.js";
      script.async = true;
      script.onload = function () {
        if (window.Hls) {
          resolve(window.Hls);
          return;
        }
        hlsRequest = null;
        reject(new Error("Hls missing"));
      };
      script.onerror = function () {
        hlsRequest = null;
        reject(new Error("Hls failed"));
      };
      document.head.appendChild(script);
    });
    return hlsRequest;
  }

  function preferNativeHLS(video) {
    if (!video.canPlayType("application/vnd.apple.mpegurl")) {
      return false;
    }
    // Chrome reports "maybe" for HLS but cannot play it. Safari can.
    return /Safari/i.test(navigator.userAgent) &&
      !/(Chrome|Chromium|CriOS|Edg|OPR|Firefox|FxiOS|Android)/i.test(navigator.userAgent);
  }

  function playWhenReady(video) {
    video.play().catch(function () {});
  }

  function fail(video, button) {
    video.classList.add("video-failed");
    video._binternetStarted = false;
    video.controls = false;
    button.hidden = false;
  }

  function playNative(video, source) {
    video.src = source;
    video.load();
    playWhenReady(video);
  }

  function attachHLS(video, source, button) {
    var hls = new window.Hls({ enableWorker: false, capLevelToPlayerSize: true, maxBufferLength: 20 });
    hls.loadSource(source);
    hls.attachMedia(video);
    hls.on(window.Hls.Events.MANIFEST_PARSED, function () {
      if (video.paused) {
        playWhenReady(video);
      }
    });
    hls.on(window.Hls.Events.ERROR, function (_, data) {
      if (!data || !data.fatal) {
        return;
      }
      hls.destroy();
      video._binternetHLS = null;
      video.removeAttribute("src");
      try {
        video.load();
      } catch (_) {
        // Ignore a reset failure; the play button is available again.
      }
      fail(video, button);
    });
    video._binternetHLS = hls;
    // Keep the click's user activation so audible playback can start.
    playWhenReady(video);
  }

  function startWithHLS(video, source, button) {
    if (window.Hls && window.Hls.isSupported()) {
      attachHLS(video, source, button);
      return;
    }
    ensureHls().then(function () {
      if (window.Hls && window.Hls.isSupported()) {
        attachHLS(video, source, button);
        return;
      }
      if (video.canPlayType("application/vnd.apple.mpegurl")) {
        playNative(video, source);
        return;
      }
      fail(video, button);
    }).catch(function () {
      if (video.canPlayType("application/vnd.apple.mpegurl")) {
        playNative(video, source);
        return;
      }
      fail(video, button);
    });
  }

  function loadPoster(video) {
    var poster = video.getAttribute("data-poster");
    if (poster && !video.poster) {
      video.poster = poster;
    }
  }

  function start(video, button) {
    if (video._binternetStarted) {
      return;
    }
    video._binternetStarted = true;
    button.hidden = true;
    video.classList.remove("video-failed");
    video.controls = true;
    loadPoster(video);

    var hlsSource = video.getAttribute("data-hls");
    var source = video.getAttribute("data-src");

    if (hlsSource) {
      if (preferNativeHLS(video)) {
        playNative(video, hlsSource);
        return;
      }
      startWithHLS(video, hlsSource, button);
      return;
    }

    if (source) {
      playNative(video, source);
      return;
    }

    fail(video, button);
  }

  function initPosters(videos) {
    if (!("IntersectionObserver" in window)) {
      for (var index = 0; index < videos.length; index += 1) {
        loadPoster(videos[index]);
      }
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      for (var index = 0; index < entries.length; index += 1) {
        if (!entries[index].isIntersecting) {
          continue;
        }
        loadPoster(entries[index].target);
        observer.unobserve(entries[index].target);
      }
    }, { rootMargin: "300px 0px" });

    for (var index = 0; index < videos.length; index += 1) {
      if (index < 4) {
        loadPoster(videos[index]);
      } else {
        observer.observe(videos[index]);
      }
    }
  }

  function init() {
    var videos = document.querySelectorAll("video[data-hls], video[data-src]");
    initPosters(videos);

    for (var index = 0; index < videos.length; index += 1) {
      (function (video) {
        var button = video.parentNode ? video.parentNode.querySelector(".video-play") : null;
        if (!button) {
          return;
        }
        button.addEventListener("click", function () {
          start(video, button);
        });
      })(videos[index]);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
