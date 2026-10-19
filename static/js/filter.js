// Submit the search form when the media filter changes. The select lives in
// the search form, so the query and filter travel together.
(function () {
  "use strict";

  var selects = document.querySelectorAll(".search-container select[name='media']");
  for (var index = 0; index < selects.length; index += 1) {
    selects[index].addEventListener("change", function () {
      if (this.form) {
        this.form.submit();
      }
    });
  }
})();
