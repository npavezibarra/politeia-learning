(function ($) {
  function init() {
    var $search = $("#pl_product_owner_search");
    var $id = $("#pl_product_owner_id");
    var $clear = $("#pl_product_owner_clear");

    if (!$search.length) return;

    $search.autocomplete({
      minLength: 2,
      delay: 200,
      source: function (request, response) {
        $.ajax({
          url: PLWooProductOwner.ajaxUrl,
          dataType: "json",
          data: {
            action: "pl_woo_user_search",
            nonce: PLWooProductOwner.nonce,
            term: request.term,
          },
        })
          .done(function (res) {
            if (!res || !res.success) {
              response([]);
              return;
            }
            response(res.data || []);
          })
          .fail(function () {
            response([]);
          });
      },
      select: function (event, ui) {
        if (!ui || !ui.item) return;
        $id.val(ui.item.id || "");
        $search.val(ui.item.label || "");
        return false;
      },
      change: function (event, ui) {
        // If user typed something but didn't select an option, clear the id.
        if (!ui || !ui.item) {
          $id.val("");
        }
      },
    });

    $clear.on("click", function () {
      $search.val("");
      $id.val("");
    });
  }

  $(init);
})(jQuery);

