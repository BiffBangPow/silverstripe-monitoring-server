<div class="bbp-monitoring_client_connection_status">
    <h2><%t BBPMonitoring.connectionstatus 'Connection status' %></h2>

    <% if $Status == 'error' %>
        <p class="bbp-monitoring_client_disconnected"><%t BBPMonitoring.connectionerror 'No communication with the target client has been possible.  Please check the configuration, and installed software.' %></p>
    <% else_if $Status == 'warning' %>
        <p class="bbp-monitoring_client_connection_warning"><%t BBPMonitoring.connectionwarning 'Last connection at {lastconn} - please check client is still active and online' lastconn=$LastFetchFormatted %></p>
    <% else_if $Status == 'ok' %>
        <p class="bbp-monitoring_client_connection_ok"><%t BBPMonitoring.connectionwarning 'Client OK - Last connection at {lastconn}' lastconn=$LastFetchFormatted %></p>
    <% else %>
        <p class="bbp-monitoring_client_connection_unknown"><%t BBPMonitoring.connectionuknown 'Connection status unknown' %></p>
    <% end_if %>
</div>
