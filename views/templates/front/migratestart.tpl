{$HOOK_HEADER}

{if isset($HOOK_HOME_TAB_CONTENT) && $HOOK_HOME_TAB_CONTENT|trim}
    {if isset($HOOK_HOME_TAB) && $HOOK_HOME_TAB|trim}
        <ul id="home-page-tabs" class="nav nav-tabs clearfix">
            {$HOOK_HOME_TAB}
        </ul>
    {/if}
    <div class="tab-content">{$HOOK_HOME_TAB_CONTENT}</div>
{/if}
{if isset($HOOK_HOME) && $HOOK_HOME|trim}
    <div class="clearfix">{$HOOK_HOME}</div>
{/if}

{literal}
    <script type="text/javascript">
        (function () {
            popupContinue = function (event) {
                event.style.display = 'none';
                var loader = event.nextElementSibling;
                loader.style.display = 'block';
                loader.nextElementSibling.style.display = 'none';

                var host = window.location.origin,
                        parameters = window.location.search,
                        uuid;

                parameters = parameters.split('&');

                for (var parameter in parameters) {
                    if (parameters[parameter].indexOf('uuid') != -1) {
                        uuid = parameters[parameter].split('=')[1];
                    }
                }

                window.location.replace(host + '?controller=migratecomplete&fc=module&module=expressly&uuid=' + uuid);
            };

            popupClose = function (event) {
                window.location.replace(window.location.origin);
            };

            openTerms = function (event) {
                window.open(event.href, '_blank');
            };

            openPrivacy = function (event) {
                window.open(event.href, '_blank');
            };
        })();
    </script>
{/literal}
{$EXPRESSLY_POPUP}