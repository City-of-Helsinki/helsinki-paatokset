/**
 * @file
 * meetings_calendar.js
 *
 * Creates vue applcation for the meetings calendar
 */

 (function($, Drupal) {

  Drupal.behaviors.paatoksetAhjoMeetingsCalendar = {
    attach() {
      const markup = `
      <div class="meetings-calendar container">
        <div class="calendar-header">
          <i @click="selectPrevious" class="hds-icon hds-icon--angle-left"></i>
          <h2>{{ selectedMonth }} {{ year }}</h2>
          <i @click="selectNext" class="hds-icon hds-icon--angle-right"></i>
        </div>
        <div class="calendar-month">
          <ol class="days-grid">
            <li
              v-for="day in daysWithMeetings"
              :key="day.date"
              class="calendar-day"
              :class="{
                'calendar-day__today' : isToday(day.date),
                'calendar-day__no-meetings' : day.meetings.length === 0
              }"
              >
              <div class="date-header">
                <span>{{ getDay(day.date) }}.</span>
                <span>{{ formatDay(day.date) }}.</span>
              </div>
              <template v-if="day.meetings.length > 0">
                <div
                v-for="meeting in day.meetings"
                class="meeting-row"
                >
                  <h5 class="meeting-title">{{meeting.title}}</h5>
                  <div class="meeting-start-time">{{ meeting.start_time}}</div>
                  <template v-if="meeting.decision_link">
                    <a :href="meeting.minutes_link">
                      {{ openDecisions }}
                      <i class="hds-icon hds-icon--angle-right"></i>
                    </a>
                  </template>
                  <template v-else-if="meeting.minutes_link && meeting.motions_list_link">
                    <a :href="meeting.minutes_link">
                      {{ openMinutes }}
                      <i class="hds-icon hds-icon--angle-right"></i>
                    </a>
                  </template>
                  <template v-else>
                    <a v-if="meeting.motions_list_link" :href="meeting.motions_list_link">
                      {{ openMotions }}
                      <i class="hds-icon hds-icon--angle-right"></i>
                    </a>
                  </template>
                </div>
              </template>
              <template v-else>
                <div class="no-meetings meeting-title">{{ Drupal.t('Ei kokouksia')}}</div>
              </template>
              
            </li>
          </ol>
        </div>
      </div>
      `
      
      /* URL to get all meetings */
      const yearAgo = dayjs().subtract(1, "year").format("YYYY-MM-DD");
      const dataURL = window.location.origin + '/fi/ahjo_api/meetings?from=' + yearAgo;

      new Vue({
        el: '#meetings-calendar-vue',
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
