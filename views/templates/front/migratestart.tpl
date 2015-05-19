{literal}
    <script type="text/javascript">
        //    var trigger = document.getElementsByClassName('expressly_button')[0].getElementsByTagName('a')[0];
        //    trigger.addEventListener('click', popupContinue);
        (function () {
            window.popupContinue = function () {
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
        })();
    </script>
{/literal}
{$xly_popup}