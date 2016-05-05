(function ($) {

/**
 * Attaches the autocomplete behavior to all required fields.
 */
Drupal.behaviors.ebi_ols = {
  attach: function (context) {
    var $context = $(context);
    $context.find('a.ebi_ols_formatter_default').each(function(index, value){
      var URL = $(this).attr('ols_url');
      var item = $(this);
      $.ajax({
        url : URL,
        success : function(data) {
          item.text(data.label);
        },
        dataType : 'json',
      });
    });

    $.ui.autocomplete.prototype._renderItem = function (ul, item) {
      return $("<li></li>").data("item.autocomplete", item).append("<a>" + item.label + "</a>").appendTo(ul);
    };
    var URL_PREFIX = "http://www.ebi.ac.uk/ols/api/select?ontology=";
    $context.find('input.ols-autocomplete').autocomplete ({
      source: function (request, response) {
        var ontology = this.element.attr('ontology');
        var URL = URL_PREFIX + this.element.attr('ontology') + "&rows=1000&q=" + this.element.val();
        $.ajax({
          url : URL,
          success : function(data) {
            var suggestions = [];
            $.each(data.highlighting, function(key, value) {
              if (value.label_autosuggest) {
                var label = value.label_autosuggest.join();
                if (value.synonym_autosuggest) {
                  label = label + ' (' + value.synonym_autosuggest.join() + ')';
                }
                suggestions.push({value: key, label: label});
              } else {
                return false;
              }
            });
            if (suggestions.length == 0) {
              $.each(data.response.docs, function(key, value) {
                suggestions.push({value: value.iri, label: value.label + ' (' + value.iri + ')'});
              });
            }
            response(suggestions);
          },
          dataType : 'json',
        });
      }
    });
    /*
    $context.find('input.ols-autocomplete').bind('focus', function(){
      if($(this).val()!=""){
         $(this).autocomplete("search");
      }
    });
    */
  }
};

})(jQuery);
