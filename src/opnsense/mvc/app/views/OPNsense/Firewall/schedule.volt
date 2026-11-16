{#
 # Copyright (c) 2026 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<script>
    $(function () {
        const formatters = {
            text: function (column, row) {
                return $('<span>').text(row[column.id] || '').html();
            }
        };
        const schedules = $('#{{formGridSchedule["table_id"]}}').UIBootgrid({
            search: '/api/firewall/schedule/search_item/',
            get: '/api/firewall/schedule/get_item/',
            add: '/api/firewall/schedule/add_item/',
            set: '/api/firewall/schedule/set_item/',
            del: '/api/firewall/schedule/del_item/',
            options: {
                selection: true,
                multiSelect: false,
                rowSelect: true,
                stickySelect: true,
                formatters: formatters
            },
            commands: {
                copy: {
                    filter: function () {
                        return false;
                    }
                }
            }
        }).on('selected.rs.jquery.bootgrid deselected.rs.jquery.bootgrid', function () {
            $('#schedule-ranges').toggle(schedules.bootgrid('getSelectedRows').length > 0);
            $('#{{formGridRange["table_id"]}}').bootgrid('reload');
        }).on('loaded.rs.jquery.bootgrid', function () {
            const rows = schedules.bootgrid('getCurrentRows');
            if (!schedules.bootgrid('getSelectedRows').length && rows.length) {
                schedules.bootgrid('select', [rows[0].uuid]);
            }
            $('#schedule-ranges').toggle(schedules.bootgrid('getSelectedRows').length > 0);
            $('#{{formGridRange["table_id"]}}').bootgrid('reload');
        });
        $('#{{formGridRange["table_id"]}}').UIBootgrid({
            search: '/api/firewall/schedule/search_range/',
            get: '/api/firewall/schedule/get_range/',
            add: '/api/firewall/schedule/add_range/',
            set: '/api/firewall/schedule/set_range/',
            del: '/api/firewall/schedule/del_range/',
            options: {
                formatters: formatters,
                requestHandler: function (request) {
                    const selected = schedules.bootgrid('getSelectedRows');
                    request.schedule = selected.length ? selected[0] : '';
                    return request;
                }
            },
            commands: {
                copy: {
                    filter: function () {
                        return false;
                    }
                }
            }
        });
        /* a bit of trickery to add heading texts */
        $("div.actionBar").each(function(){
            let heading_text = "";
            if ($(this).closest(".bootgrid-header").attr("id") === "{{formGridSchedule['table_id']}}-header") {
                heading_text = "{{ lang._('Schedule') }}";
            } else {
                heading_text = "{{ lang._('Time ranges') }}";
            }
            $(this).parent().prepend($('<div class="col-sm-2 theading-text">'+heading_text+'</div>'));
            $(this).removeClass("col-sm-12");
            $(this).addClass("col-sm-10");
        });
    });
</script>

<div class="content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridSchedule) }}
    {{ partial('layout_partials/base_bootgrid_table', formGridRange) }}
</div>
{{ partial('layout_partials/base_dialog', [
    'fields': formDialogSchedule,
    'id': formGridSchedule['edit_dialog_id'],
    'label': lang._('Edit schedule')
]) }}
{{ partial('layout_partials/base_dialog', [
    'fields': formDialogRange,
    'id': formGridRange['edit_dialog_id'],
    'label': lang._('Edit time range')
]) }}
