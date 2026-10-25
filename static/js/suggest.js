// Fill a native datalist with Pinterest search suggestions as the visitor types.
(function () {
  "use strict";

  var input = document.querySelector("input[name='q']");
  if (!input) {
    return;
  }

  var list = document.createElement("datalist");
  list.id = "search-suggestions";
  document.body.appendChild(list);
  input.setAttribute("list", list.id);

  var timer = null;
  var latest = 0;

  input.addEventListener("input", function () {
    var term = input.value.trim();
    clearTimeout(timer);
    if (term.length < 2) {
      list.textContent = "";
      return;
    }

    timer = setTimeout(function () {
      var request = ++latest;
      fetch("/suggest.php?q=" + encodeURIComponent(term))
        .then(function (response) {
          return response.ok ? response.json() : [];
        })
        .then(function (items) {
          if (request !== latest || !Array.isArray(items)) {
            return;
          }
          list.textContent = "";
          items.slice(0, 8).forEach(function (item) {
            var option = document.createElement("option");
            option.value = item;
            list.appendChild(option);
          });
        })
        .catch(function () {});
    }, 200);
  });
})();
