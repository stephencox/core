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
        const escapeText = value => $('<span>').text(value).html();
        const splitList = value => value ? String(value).split(',') : [];
        const displayTimes = value => (value || '00:00-23:59').split('-').map(time => {
            return time.split(':').map(part => part.padStart(2, '0')).join(':');
        });
        const dateLabel = new Intl.DateTimeFormat(undefined, {month: 'long', day: 'numeric'});
        const weekdayLabel = new Intl.DateTimeFormat(undefined, {weekday: 'long'});
        const weekdays = Array.from({length: 7}, (_, index) => weekdayLabel.format(new Date(2000, 0, 3 + index)));
        const formatters = {
            text: function (column, row) {
                return escapeText(row[column.id] || '');
            },
            weekdays: function (column, row) {
                return escapeText(splitList(row.position).map(day => weekdays[Number(day) - 1] || day).join(', '));
            },
            dates: function (column, row) {
                const months = splitList(row.month);
                return escapeText(splitList(row.day).map((day, index) => {
                    const date = new Date(2000, Number(months[index]) - 1, Number(day));
                    return Number.isNaN(date.getTime()) ? (months[index] || '?') + '/' + day : dateLabel.format(date);
                }).join(', '));
            },
            hours: function (column, row) {
                return escapeText(displayTimes(row.hour).join(' – '));
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
        const rangeFormatters = {};
        Object.entries(formatters).forEach(([name, formatter]) => {
            rangeFormatters[name] = (column, row) => {
                return row[column.id] === '' || row[column.id] == null ? '*' : formatter(column, row);
            };
        });
        $('#{{formGridRange["table_id"]}}').UIBootgrid({
            search: '/api/firewall/schedule/search_range/',
            get: '/api/firewall/schedule/get_range/',
            add: '/api/firewall/schedule/add_range/',
            set: '/api/firewall/schedule/set_range/',
            del: '/api/firewall/schedule/del_range/',
            options: {
                formatters: rangeFormatters,
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
        const rangeDialog = $('#{{formGridRange["edit_dialog_id"]}}');
        const field = id => rangeDialog.find('[id="' + id + '"]');
        const row = id => field(id).closest('tr');
        let mapping = false;

        // The calendar edits paired month/day lists; the reference leap year is never stored.
        $.fn.datepicker.dates.schedule = $.extend({}, $.fn.datepicker.dates.en, {
            days: Array.from({length: 7}, (_, day) => weekdayLabel.format(new Date(2000, 0, 2 + day))),
            daysShort: Array.from({length: 7}, (_, day) => new Intl.DateTimeFormat(undefined, {
                weekday: 'short'
            }).format(new Date(2000, 0, 2 + day))),
            daysMin: Array.from({length: 7}, (_, day) => new Intl.DateTimeFormat(undefined, {
                weekday: 'narrow'
            }).format(new Date(2000, 0, 2 + day))),
            months: Array.from({length: 12}, (_, month) => new Intl.DateTimeFormat(undefined, {
                month: 'long'
            }).format(new Date(2000, month, 1))),
            titleFormat: 'MM',
            clear: "{{ lang._('Clear') }}"
        });
        field('editor.dates').datepicker({
            language: 'schedule',
            format: {
                toDisplay: function (date) {
                    return String(date.getUTCMonth() + 1).padStart(2, '0') + '/' +
                        String(date.getUTCDate()).padStart(2, '0');
                },
                toValue: function (value) {
                    const [month, day] = value.split('/').map(Number);
                    return new Date(Date.UTC(2000, month - 1, day));
                }
            },
            multidate: true,
            weekStart: 1,
            clearBtn: true,
            maxViewMode: 0,
            defaultViewDate: new Date(2000, 0, 1),
            startDate: new Date(2000, 0, 1),
            endDate: new Date(2000, 11, 31),
            container: rangeDialog
        }).on('changeDate clearDate', function () {
            if (mapping) {
                return;
            }
            const dates = $(this).datepicker('getDates');
            field('timerange.month').val(dates.map(date => date.getMonth() + 1).join(','));
            field('timerange.day').val(dates.map(date => date.getDate()).join(','));
        });
        ['timerange.month', 'timerange.day', 'timerange.hour'].forEach(id => row(id).hide());
        // Keep model validation messages beside the controls that edit those values.
        field('help_block_timerange.month').add(field('help_block_timerange.day'))
            .appendTo(row('editor.mode').children('td').last());
        field('help_block_timerange.hour').appendTo(row('editor.stop').children('td').last());

        function showRangeFields() {
            const calendar = field('editor.mode').val() === 'dates';
            row('timerange.position').toggle(!calendar);
            row('editor.dates').toggle(calendar);
        }

        field('editor.mode').on('change', function () {
            if (!mapping) {
                if ($(this).val() === 'dates') {
                    field('timerange.position').val([]).selectpicker('refresh');
                } else {
                    field('editor.dates').datepicker('clearDates');
                    field('timerange.month').add(field('timerange.day')).val('');
                }
            }
            showRangeFields();
        });
        field('editor.start').add(field('editor.stop')).on('change', function () {
            if (!mapping) {
                field('timerange.hour').val(field('editor.start').val() + '-' +
                    field('editor.stop').val());
            }
        });
        rangeDialog.on('opnsense_bootgrid_mapped', function () {
            mapping = true;
            field('editor.mode').empty()
                .append($('<option>').val('weekdays').text("{{ lang._('Every week') }}"))
                .append($('<option>').val('dates').text("{{ lang._('Every year on selected dates') }}"))
                .val(field('timerange.month').val() ? 'dates' : 'weekdays').selectpicker('refresh');
            const months = splitList(field('timerange.month').val());
            const days = splitList(field('timerange.day').val());
            field('editor.dates').datepicker('setDates', days.map((day, index) => {
                return new Date(2000, Number(months[index]) - 1, Number(day));
            }));
            field('timerange.hour').val(field('editor.start').val() + '-' + field('editor.stop').val());
            // Preserve duplicate dates and date order until the calendar selection changes.
            mapping = false;
            showRangeFields();
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
