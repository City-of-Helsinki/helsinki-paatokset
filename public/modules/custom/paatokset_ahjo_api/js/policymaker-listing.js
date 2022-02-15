/**
 * @file
 * meetings_calendar.js
 *
 * Limits visible policymakers to 50 and adds load more functionality
 */

 (function($, Drupal) {

  

  Drupal.behaviors.PolicymakerListing = {
    attach: function (context) {
        $(document).ready(function(){
          const accordions = $('.accordion-item__content__inner', context)

          accordions.each(function() {
            console.log($(this).children().length)
            if($(this).children().length > 50) {

              let children = $(this).children()
              $(this).find('.load-more-btn', context).css( 'display', 'inline-flex' );

              children.each(function() {
                if($(this).attr('class') === 'policymaker-row__link' || $(this).attr('class') === 'sector-title')
                $(this).hide()
              })

              children.slice(0, 50).css( "display", "flex" )

              let x = 50

              $(this).find('.load-more-btn', context).click(function (e) {
                e.preventDefault();

                children.slice(x, x + 50).slideDown();
                x = x + 50

                if (x > children.length) {
                  $(this).css( "display", "none" );
                }
              })
            }
          });
        })
    
    }
  }

}(jQuery, Drupal));
