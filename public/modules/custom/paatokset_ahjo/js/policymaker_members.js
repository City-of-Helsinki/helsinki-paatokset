/**
 * @file
 * meetings_calendar.js
 *
 * Creates vue applcation for the meetings calendar
 */

 (function($, Drupal) {

  Drupal.behaviors.paatoksetAhjoPolicymakerMembers = {
    attach() {
      const markup = `
      <div class="policymaker-members container">
        testi testinen
      </div>
      `
      
      /* URL to get all meetings */
      const yearAgo = dayjs().subtract(1, "year").format("YYYY-MM-DD");
      const dataURL = window.location.origin + '/fi/ahjo_api/meetings?from=' + yearAgo;

      new Vue({
        el: '#policymaker-members-vue',
        template: markup,
        data: {
          meetings: [],
          daysWithMeetings:[],
          isReady: false,
          selectedDate: dayjs(),
          currentDate: dayjs().format("YYYY-MM-DD"),
        },
        methods: {
          getJson() {
            const self = this;
            $.getJSON(dataURL, function(data) {
              self.meetings = data.data;
            })
            .done(function( json ) {
              self.isReady = true;
              let temp = self.days;
              temp.forEach(function (day) {
                var date = day.date;
                if(self.meetings[date]) {
                  day.meetings = self.meetings[date]
                }
              });
              
              self.daysWithMeetings = temp;
            })
          },
          selectPrevious() {
            let newSelectedDate = dayjs(this.selectedDate).subtract(1, "month");
            this.selectedDate = newSelectedDate;
            const self = this;
            let temp = this.days;
            temp.forEach(function (day) {
              var date = day.date;
              if(self.meetings[date]) {
                day.meetings = self.meetings[date]
              }
            });

            self.daysWithMeetings = temp;
          },
          selectNext() {
            let newSelectedDate = dayjs(this.selectedDate).add(1, "month");
            this.selectedDate = newSelectedDate;

            const self = this;
            let temp = this.days;
            temp.forEach(function (day) {
              var date = day.date;
              if(self.meetings[date]) {
                day.meetings = self.meetings[date]
              }
            });

            self.daysWithMeetings = temp;
          },
          formatDay(date) {
            return dayjs(date).format("D.M");
          },
          getDay(date) {
            if(dayjs(date).day() === 1) {
              return Drupal.t('Maanantai');
            }
            if(dayjs(date).day() === 2) {
              return Drupal.t('Tiistai');
            }
            if(dayjs(date).day() === 3) {
              return Drupal.t('Keskiviikko');
            }
            if(dayjs(date).day() === 4) {
              return Drupal.t('Torstai');
            }
            if(dayjs(date).day() === 5) {
              return Drupal.t('Perjantai');
            }
            if(dayjs(date).day() === 6) {
              return Drupal.t('Lauantai');
            }
            if(dayjs(date).day() === 0) {
              return Drupal.t('Sunnuntai');
            }
          },
          isToday(date) {
            return dayjs(date).format("YYYY-MM-DD") === this.currentDate;
          },
          isPast(date) {
            return dayjs(date).format("YYYY-MM-DD") < this.currentDate;
          },
          getWeekday(date) {
            return dayjs(date).weekday();
          },
        },
        computed: {
          selectedMonth() {
            return Drupal.t(this.selectedDate.format("MMMM"), {}, {context: "Long month name"});
          },
          weekdays() {
            return weekdaysData;
          },
          today() {
            return dayjs().format("YYYY-MM-DD");
          },
          month() {
            return Number(this.selectedDate.format("M"));
          },
          year() {
            return Number(this.selectedDate.format("YYYY"));
          },
          numberOfDaysInMonth() {
            return dayjs(this.selectedDate).daysInMonth();
          },
          openMotions(){
            return Drupal.t('Avaa esityslista')
          },
          openMinutes() {
            return Drupal.t('Katso pöytäkirja')
          },
          openDecisions() {
            return Drupal.t('Katso päätöstiedote')
          },
          days() {
            let allDays = [];
            if(this.previousMonthDays) {
              allDays = [
                ...this.previousMonthDays,
                ...this.currentMonthDays,
                ...this.nextMonthDays
              ];
            } else {
              allDays = [
                ...this.currentMonthDays,
                ...this.nextMonthDays
              ];
            }

            let temp = allDays
            temp = temp.filter(result => dayjs(result.date).day() !== 6 && dayjs(result.date).day() !== 0);
            return temp;
          },
          currentMonthDays() {
            return [...Array(this.numberOfDaysInMonth)].map((day, index) => {
              return {
                date: dayjs(`${this.year}-${this.month}-${index + 1}`).format("YYYY-MM-DD"),
                isCurrentMonth: true,
                meetings: []
              };
            });
          },
          previousMonthDays() {
            const firstDayOfTheMonthWeekday = dayjs(this.currentMonthDays[0].date).day();
            const previousMonth = dayjs(`${this.year}-${this.month}-01`).subtract(1, "month");

            const visibleNumberOfDaysFromPreviousMonth = firstDayOfTheMonthWeekday ? firstDayOfTheMonthWeekday - 1 : 6;
        
            const previousMonthLastMondayDayOfMonth = dayjs(this.currentMonthDays[0].date).subtract(visibleNumberOfDaysFromPreviousMonth, "day").date();
            
            //Doesn't add previous days if the first day of the month is saturday or sunday
            if(firstDayOfTheMonthWeekday !== 6 && firstDayOfTheMonthWeekday !== 0) {
              return [...Array(visibleNumberOfDaysFromPreviousMonth)].map((day, index) => {
                return {
                  date: dayjs(`${previousMonth.year()}-${previousMonth.month() + 1}-${previousMonthLastMondayDayOfMonth + index}`).format("YYYY-MM-DD"),
                  isCurrentMonth: false,
                  meetings: []
                };
              });
            }
          },
          nextMonthDays() {
            const lastDayOfTheMonthWeekday = dayjs(`${this.year}-${this.month}-${this.currentMonthDays.length}`).day();
            const nextMonth = dayjs(`${this.year}-${this.month}-01`).add(1, "month");
            const visibleNumberOfDaysFromNextMonth = lastDayOfTheMonthWeekday ? 7 - lastDayOfTheMonthWeekday : lastDayOfTheMonthWeekday;
        
            return [...Array(visibleNumberOfDaysFromNextMonth)].map((day, index) => {
              return {
                date: dayjs(`${nextMonth.year()}-${nextMonth.month() + 1}-${index + 1}`).format("YYYY-MM-DD"),
                isCurrentMonth: false,
                meetings: []
              };
            });
          }
        },
        mounted() {
          this.getJson();
        },
      });
    }
  }

}(jQuery, Drupal));
