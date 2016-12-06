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

<script type="text/javascript">
    (function () {
        setTimeout(function() {
            var login = confirm('Your email address{if isset($EMAIL) && $EMAIL neq ''} ({$EMAIL}){/if} has already been registered on this store. Please login with your credentials. Pressing OK will redirect you to the login page.');
            if (login) {
                window.location.replace('{$shop_base_url}/index.php?controller=authentication');
            }
        }, 500);
    })();
</script>