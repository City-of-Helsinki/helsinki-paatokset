/**
 * @file
 * meetings_calendar.js
 *
 * Creates vue applcation for the meetings calendar
 */

(function($, Drupal, drupalSettings) {

  Drupal.behaviors.paatoksetAhjoMeetingsCalendar = {
    attach() {
      const markup = `
      <div class="meetings-calendar container">
        <div v-if="!isReady" class="hds-loading-spinner">
          <div></div>
          <div></div>
          <div></div>
        </div>
        <div v-if="isReady" class="calendar-header">
          <div class="icon-container" @click="selectPrevious" @keyup.enter="selectPrevious" role="button" :aria-label="previousMonth" tabindex="0">
            <i class="hel-icon hel-icon--angle-left"></i>
          </div>
          <h2>{{ selectedMonth }} {{ year }}</h2>
          <div class="icon-container" @click="selectNext" @keyup.enter="selectNext" role="button" :aria-label="nextMonth" tabindex="0">
            <i class="hel-icon hel-icon--angle-right"></i>
          </div>
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
              <div class="date-header" role="heading" aria-level="3">
                <span>{{ getDay(day.date) }}</span>
                <span>{{ formatDay(day.date) }}.</span>
              </div>
              <template v-if="day.meetings.length > 0">
                <div
                v-for="meeting in day.meetings"
                class="meeting-row"
                :class="meeting.meeting_cancelled ? 'meeting-row--cancelled': ''"
                >
                  <h4 class="meeting-title">
                    {{ meeting.policymaker_name }}
                    <span v-if="meeting.meeting_moved">
                      ({{ meetingMoved }})
                    </span>
                    <span v-else-if="meeting.meeting_cancelled">
                      ({{ meetingCancelled }})
                    </span>
                  </h4>
                  <div class="meeting-start-time">{{ meeting.start_time}}</div>
                  <template v-if="meeting.decision_link">
                    <a :href="meeting.decision_link" :aria-label="openDecisions + ': ' + meeting.title + ' ' + formatDayFull(day.date)">
                      {{ openDecisions }}
                      <i class="hel-icon hel-icon--angle-right"></i>
                    </a>
                  </template>
                  <template v-else-if="meeting.minutes_link">
                    <a :href="meeting.minutes_link" :aria-label="openMinutes + ': ' + meeting.title + ' ' + formatDayFull(day.date)">
                      {{ openMinutes }}
                      <i class="hel-icon hel-icon--angle-right"></i>
                    </a>
                  </template>
                  <template v-else>
                    <a v-if="meeting.motions_list_link" :href="meeting.motions_list_link" :aria-label="openMotions + ': ' + meeting.title + ' ' + formatDayFull(day.date)">
                      {{ openMotions }}
                      <i class="hel-icon hel-icon--angle-right"></i>
                    </a>
                  </template>
                </div>
              </template>
              <template v-else>
                <div class="no-meetings meeting-title">{{ noMeetings }}</div>
              </template>
            </li>
          </ol>
        </div>
      </div>
      `

      /* URL to get all meetings */
      const startDate = dayjs().subtract(6, "month").format("YYYY-MM-DD");
      const fullStartDate = dayjs().subtract(3, "year").format("YYYY-MM-DD");
      const dataURL = window.location.origin + '/' + drupalSettings.path.pathPrefix + 'ahjo_api/meetings?from=' + startDate;
      const fullDataURL = window.location.origin + '/' + drupalSettings.path.pathPrefix + 'ahjo_api/meetings?from=' + fullStartDate;

      new Vue({
        el: '#meetings-calendar-vue',
        template: markup,
        data: {
          meetings: [],
          daysWithMeetings:[],
          isMounted: false,
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
              self.getFullJson();
            })
          },
          getFullJson() {
            const self = this;
            $.getJSON(fullDataURL, function(data) {
              self.meetings = data.data;
            })
            .done(function( json ) {
              self.isReady = true;
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
          formatDayFull(date) {
            return dayjs(date).format("DD.MM.YYYY");
          },
          getDay(date) {
            if(dayjs(date).day() === 1) {
              return window.Drupal.t('Monday', {}, {context: "Meeting calendar weekday."});
            }
            if(dayjs(date).day() === 2) {
              return window.Drupal.t('Tuesday', {}, {context: "Meeting calendar weekday."});
            }
            if(dayjs(date).day() === 3) {
              return window.Drupal.t('Wednesday', {}, {context: "Meeting calendar weekday."});
            }
            if(dayjs(date).day() === 4) {
              return window.Drupal.t('Thursday', {}, {context: "Meeting calendar weekday."});
            }
            if(dayjs(date).day() === 5) {
              return window.Drupal.t('Friday', {}, {context: "Meeting calendar weekday."});
            }
            if(dayjs(date).day() === 6) {
              return window.Drupal.t('Saturday', {}, {context: "Meeting calendar weekday."});
            }
            if(dayjs(date).day() === 0) {
              return window.Drupal.t('Sunday', {}, {context: "Meeting calendar weekday."});
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
            return window.Drupal.t(this.selectedDate.format("MMMM"), {}, {context: "Long month name"});
          },
          /**
           * Needed for the month translation to work
           */
          monthTranslations() {
            window.Drupal.t('January', {}, {context: "Long month name"})
            window.Drupal.t('February', {}, {context: "Long month name"})
            window.Drupal.t('March', {}, {context: "Long month name"})
            window.Drupal.t('April', {}, {context: "Long month name"})
            window.Drupal.t('May', {}, {context: "Long month name"})
            window.Drupal.t('June', {}, {context: "Long month name"})
            window.Drupal.t('July', {}, {context: "Long month name"})
            window.Drupal.t('August', {}, {context: "Long month name"})
            window.Drupal.t('September', {}, {context: "Long month name"})
            window.Drupal.t('October', {}, {context: "Long month name"})
            window.Drupal.t('November', {}, {context: "Long month name"})
            window.Drupal.t('December', {}, {context: "Long month name"})
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
            return window.Drupal.t('Open motions');
          },
          openMinutes() {
            return window.Drupal.t('Open minutes');
          },
          openDecisions() {
            return window.Drupal.t('Open decisions');
          },
          noMeetings() {
            return window.Drupal.t('No meetings');
          },
          meetingCancelled() {
            return window.Drupal.t('meeting cancelled', {}, {context: 'Meetings calendar'});
          },
          meetingMoved() {
            return window.Drupal.t('meeting moved', {}, {context: 'Meetings calendar'});
          },
          nextMonth() {
            return window.Drupal.t('Seuraava kuukausi');
          },
          previousMonth() {
            return window.Drupal.t('Edellinen kuukausi');
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
          if (!this.isMounted) {
            this.getJson();
            this.isMounted = true;
          }
        },
      });
    }
  }

}(jQuery, Drupal, drupalSettings));
