(function($){
    if (typeof window.pcgGroupsField === 'undefined') {
        return;
    }

    var settings = window.pcgGroupsField;
    var removeLabel = settings.labels && settings.labels.remove ? settings.labels.remove : '×';

    function initGroupsField($field){
        var $input = $field.find('.pcg-groups-input');
        var $hidden = $field.find('.pcg-groups-hidden');
        var $tagsContainer = $field.find('.pcg-groups-tags');
        var $suggestions = $field.find('.pcg-groups-suggestions');
        var selectedGroups = [];

        function readInitialSelection(){
            selectedGroups = [];
            $tagsContainer.find('[data-group-id]').each(function(){
                var $tag = $(this);
                var id = parseInt($tag.attr('data-group-id'), 10);
                var title = $tag.attr('data-group-title') || $tag.text();
                if(!isNaN(id)){
                    selectedGroups.push({ id: id, title: title });
                }
            });
            syncHiddenField();
        }

        function syncHiddenField(){
            var ids = selectedGroups.map(function(item){ return item.id; });
            $hidden.val(JSON.stringify(ids));
        }

        function renderTags(){
            $tagsContainer.empty();
            selectedGroups.forEach(function(group){
                var $tag = $('<span/>', {
                    'class': 'pcg-group-tag',
                    'data-group-id': group.id,
                    'data-group-title': group.title
                });

                $('<span/>', {
                    'class': 'pcg-group-tag__label',
                    text: group.title
                }).appendTo($tag);

                $('<button/>', {
                    type: 'button',
                    'class': 'pcg-group-tag__remove',
                    'aria-label': removeLabel,
                    html: '&times;'
                }).appendTo($tag);

                $tagsContainer.append($tag);
            });
        }

        function addGroup(group){
            if(!group || !group.id){
                return;
            }

            var exists = selectedGroups.some(function(item){
                return item.id === group.id;
            });

            if(exists){
                clearSuggestions();
                $input.val('');
                return;
            }

            selectedGroups.push(group);
            renderTags();
            syncHiddenField();
            clearSuggestions();
            $input.val('');
        }

        function removeGroup(id){
            selectedGroups = selectedGroups.filter(function(item){
                return item.id !== id;
            });
            renderTags();
            syncHiddenField();
        }

        function clearSuggestions(){
            $suggestions.empty().hide();
        }

        function showSuggestions(results){
            $suggestions.empty();
            if(!results || !results.length){
                $suggestions.hide();
                return;
            }

            var $list = $('<ul/>', { 'class': 'pcg-groups-suggestions__list' });

            results.forEach(function(item){
                var $option = $('<li/>', {
                    'class': 'pcg-groups-suggestions__item',
                    text: item.title,
                    'data-id': item.id
                });

                $option.on('mousedown', function(e){
                    e.preventDefault();
                    addGroup({ id: item.id, title: item.title });
                });

                $list.append($option);
            });

            $suggestions.append($list).show();
        }

        function searchGroups(query){
            if(!query || query.length < 2){
                clearSuggestions();
                return;
            }

            $.ajax({
                url: settings.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: settings.action,
                    nonce: settings.nonce,
                    q: query
                }
            }).done(function(response){
                if(response && response.success){
                    showSuggestions(response.data);
                } else {
                    clearSuggestions();
                }
            }).fail(clearSuggestions);
        }

        $input.on('input', function(){
            var value = $(this).val();
            searchGroups(value);
        });

        $input.on('keydown', function(event){
            if(event.key === 'Enter'){
                event.preventDefault();
                var $firstSuggestion = $suggestions.find('.pcg-groups-suggestions__item').first();
                if($firstSuggestion.length){
                    addGroup({
                        id: parseInt($firstSuggestion.data('id'), 10),
                        title: $firstSuggestion.text()
                    });
                }
            } else if(event.key === 'Backspace' && !$(this).val().length && selectedGroups.length){
                removeGroup(selectedGroups[selectedGroups.length - 1].id);
            }
        });

        $tagsContainer.on('click', '.pcg-group-tag__remove', function(){
            var $tag = $(this).closest('[data-group-id]');
            var id = parseInt($tag.attr('data-group-id'), 10);
            if(!isNaN(id)){
                removeGroup(id);
            }
        });

        $(document).on('click', function(event){
            if(!$(event.target).closest('.pcg-groups-field').length){
                clearSuggestions();
            }
        });

        readInitialSelection();
    }

    $(document).ready(function(){
        $('.pcg-groups-field').each(function(){
            initGroupsField($(this));
        });
    });
})(jQuery);
