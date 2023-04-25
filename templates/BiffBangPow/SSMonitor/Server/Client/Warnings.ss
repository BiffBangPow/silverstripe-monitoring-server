<% if $Warnings %>
    <div class="bbp-monitoring_client_warnings">
        <h2><%t BBPMonitoring.warningspresent 'Warnings are present:' %></h2>
        <ul>
        <% loop $Warnings %>
            <li>$Message</li>
        <% end_loop %>
        </ul>
    </div>
<% end_if %>
