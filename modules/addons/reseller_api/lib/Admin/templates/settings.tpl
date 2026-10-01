<h2>{$title}</h2>
<form method="post" action="{$modulelink}&action=settings">
    <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
        <tr><td class="fieldlabel" width="20%">API Enabled</td><td class="fieldarea"><input type="checkbox" name="api_enabled" value="1" /></td></tr>
        <tr><td class="fieldlabel">Maintenance Mode</td><td class="fieldarea"><input type="checkbox" name="maintenance_mode" value="1" /></td></tr>
        <tr><td class="fieldlabel">Billing Mode</td><td class="fieldarea">
            <select name="billing_mode">
                <option value="free">Free</option>
                <option value="tracked">Tracked</option>
                <option value="statement">Statement</option>
            </select>
        </td></tr>
    </table>
    <div class="btn-container">
        <input type="submit" value="Save Changes" class="btn btn-primary" />
    </div>
</form>
