(function ($) {
Drupal.behaviors.ebi_ols = {
  attach: function (context) {
    var $context = $(context);
    $context.find('a.ebi_ols_formatter_default').each(function(index, element){
      $.ajax({
        url : iriToURL($(element).text()),
        success : function(data) {
          $(element).text(data.label);
        },
        dataType : 'json',
      });
    });
    $context.find('input.ols-autocomplete').each(function(index, element){
      $.ajax({
        url : iriToURL($(element).val()),
        success : function(data) {
          if (data.hasOwnProperty('is_obsolete')) {
          }
          $(element).siblings('.edam-tag').remove();
          $(element).after("<div class='edam-tag'>" + data.label + "</div>");
        },
        dataType : 'json',
      });
    });
    $.ui.autocomplete.prototype._renderItem = function (ul, item) {
      return $("<li></li>").data("item.autocomplete", item).append("<a>" + item.label + "</a>").appendTo(ul);
    };
    var URL_PREFIX = "http://www.ebi.ac.uk/ols/api/select?type=class&ontology=";
    $context.find('input.ols-autocomplete').autocomplete ({
      source: function (request, response) {
        var ontology = this.element.attr('ontology');
        var URL = URL_PREFIX + this.element.attr('ontology') + "&sort=label desc&rows=1000&q=" + this.element.val();
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
          dataType: 'json',
        });
      },
      select: function (event, ui) {
        $(this).siblings('.edam-tag').remove();
        $(this).after("<div class='edam-tag'>" + ui.item.label.replace(/<(?:.|\n)*?>/gm, '') + "</div>");
      }
    });
  }
};

function iriToURL(iri) {
  var i = iri.indexOf(':');
  var ontology = iri.slice(0,i);
  var id = iri.slice(i+1);
  var URL = 'http://www.ebi.ac.uk/ols/api/ontologies/' + ontology + '/terms/' + encodeURIComponent(encodeURIComponent(id));
  return URL;
}
})(jQuery);
