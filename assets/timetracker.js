
    jQuery(document).ready(function ($){
        
        /***********************
         * Date range functions on filter bar
         */
        $('.time-btn').click(function(e){
            e.stopPropagation();
            e.preventDefault();         
            set_time($(this).data('action'),$(this).data('task') );
        });
        $('#add_time').click(function(){
            $('#manual_time').val('1');
        });
        
        // Date range stuff on filter bar
        if ($('#week-start').val()==''){
            $('#clear-week').hide();
        }
        $('#set-today').click(function(){
            document.getElementById("week-start").valueAsDate = new Date();
            document.getElementById("week-end").valueAsDate = new Date();
            $(this).hide();
            $('#clear-week').show();
        });
        $('#set-week').click(function(){
            var d = new Date();
            var day = d.getDay();
            var diff = d.getDate() - day + (day == 0 ? -7:-1); // adjust when day is sunday
            var start = new Date(d.setDate(diff));
            var end = new Date(d.setDate(start.getDate()+7));
            document.getElementById("week-start").valueAsDate = start;
            document.getElementById("week-end").valueAsDate = end;
            $(this).hide();
            $('#clear-week').show();
        });
        $('#set-month').click(function(){
            var d = new Date(); 
            document.getElementById("week-start").valueAsDate = new Date(d.getFullYear(), d.getMonth(), 1);
            $(this).hide();
            $('#clear-week').show();
        });
        $('#set-last').click(function(){
            var d = new Date(); 
            document.getElementById("week-start").valueAsDate = new Date(d.getFullYear() - (d.getMonth() > 0 ? 0 : 1), (d.getMonth() - 1 + 12) % 12, 1);
            document.getElementById("week-end").valueAsDate = new Date(d.getFullYear(), d.getMonth(), 0);
            $(this).hide();
            $('#clear-week').show();
        });
        $('#clear-week').click(function(){
            $('#week-start').val('');
            $('#week-end').val('');
            $(this).hide();
            $('#set-week, #set-month').show();
        });

        $('#hide-empty').click(function(){
            var times = $('.time');
            times.each(function(){
                if ($(this).html() == '')
                $(this).closest('tr').toggle();
            });
        });
        
        $('.print-box').click(function(){
            var div_id = $(this).data('div');
            var mywindow = window.open('', 'Print Box', 'height=500,width=700');
            var start = $(this).data('start');
            var end = $(this).data('end');
            var subheading = $(this).data('sub');
            mywindow.document.write('<html><head><title>AZ Time</title><style>body{font-size:12px;font-family:arial;}table{width:700px;}table .column-workspace,.column-time{width:20%;}.screen-reader-text,.hidden,.row-actions,.column-title,.check-column,.column-author,.column-action,.column-date{display:none;}</style></head>');
            mywindow.document.write('<body>');
            mywindow.document.write('<div><h2>Anvil Zephyr, LLC</h2><p>Task and Time Report</p><p>'+subheading+'</p></div>');
            mywindow.document.write('<div><p>From: '+start+" To: "+end+"</p></div>");
            mywindow.document.write('<table><tr><td>Workspace</td><td>Task</td><td>Time</td><td>Last Activity</td></tr>'+$('#'+div_id).html()+'</table></body></html>');
            mywindow.print();
            mywindow.close();
        });


        // Adding total row to workspace table
        if (typenow=='az-workspace' || typenow=='az-task' || typenow=='az-timeslot'){
            var times = $('.time-span');
            var total = 0;
            times.each(function(){
                if ($(this).html() !== '')
                total += parseFloat($(this).html());
            });
            if (total>0){
                 var colCount = 0;
                 var time_col = 2;
                $('#the-list tr:nth-child(1) td').each(function () {
                        colCount++;
                        if ($(this).hasClass('time')){
                            time_col = colCount;
                        }
                });
                $('#the-list').append('<tr><td>&nbsp;</td><td class="row-title" colspan="'+(time_col-1)+'">Total Time</td><td class="number" style="text-align:right;">'+total.toFixed(2)+'</td><td colspan="'+(colCount-2)+'"></tr>')
            }
        }

    });
    
    function set_time(value,task){
        
        if (value == 'end_time'){
            var msg = window.prompt('Timeslot note:', '');
        }
        else var msg = '';
        jQuery.post(
            ajaxurl,
            {'action':'set_time','field':value,'task':task,'msg':msg},
            function(response){
                if (response == 'error'){
                    alert ('The action was not successful.');              
                }
                //if(value == 'delete_time' || aztt_screen == 'dashboard'){
                else window.location.reload();
                //}
            }
        );
        
        return false;

    }


