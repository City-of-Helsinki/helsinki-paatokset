// eslint-disable-next-line no-unused-vars
console.log('HEREEEEEEE');
jQuery(function($) {
  var scrollDuration = 300;
// paddles
  var leftPaddle = document.getElementsByClassName('left-paddle');
  var rightPaddle = document.getElementsByClassName('right-paddle');
// get items dimensions
  var itemsLength = $('.item').length;
  var itemSize = $('.item').outerWidth(true);
// get some relevant size for the paddle triggering point
  var paddleMargin = 20;

// get wrapper width
  var getMenuWrapperSize = function() {
    return $('.accordion-item__content__inner').outerWidth();
  }
  var menuWrapperSize = getMenuWrapperSize();
// the wrapper is responsive
  $(window).on('resize', function() {
    menuWrapperSize = getMenuWrapperSize();
    console.log(menuWrapperSize)
  });
// size of the visible part of the menu is equal as the wrapper size
  var menuVisibleSize = menuWrapperSize;

// get total width of all menu items
  var getMenuSize = function() {
    return itemsLength * itemSize;
  };
  var menuSize = getMenuSize();
  if (menuSize >= menuWrapperSize) {
    $(".paddle").addClass("hidden")
  }
  if (menuSize < menuWrapperSize){
    $(".paddle").removeClass('hidden')
  }

// get how much of menu is invisible
  var menuInvisibleSize = menuSize - menuWrapperSize;

// get how much have we scrolled to the left
  var getMenuPosition = function() {
    return $('.menu').scrollLeft();
  };

// finally, what happens when we are actually scrolling the menu
  $('.menu').on('scroll', function() {

    // get how much of menu is invisible
    menuInvisibleSize = menuSize - menuWrapperSize;
    // get how much have we scrolled so far
    var menuPosition = getMenuPosition();

    var menuEndOffset = menuInvisibleSize - paddleMargin;

    // show & hide the paddles
    // depending on scroll position
    if (menuPosition <= paddleMargin) {
      $(leftPaddle).addClass('hidden');
      $(rightPaddle).removeClass('hidden');
    } else if (menuPosition < menuEndOffset) {
      // show both paddles in the middle
      $(leftPaddle).removeClass('hidden');
      $(rightPaddle).removeClass('hidden');
    } else if (menuPosition >= menuEndOffset) {
      $(leftPaddle).removeClass('hidden');
      $(rightPaddle).addClass('hidden');
    }

    // print important values
    $('#print-wrapper-size span').text(menuWrapperSize);
    $('#print-menu-size span').text(menuSize);
    $('#print-menu-invisible-size span').text(menuInvisibleSize);
    $('#print-menu-position span').text(menuPosition);

  });

// scroll to left
  $(rightPaddle).on('click', function() {
    $('.menu').animate( { scrollLeft: menuInvisibleSize}, scrollDuration);
  });

// scroll to right
  $(leftPaddle).on('click', function() {
    $('.menu').animate( { scrollLeft: '0' }, scrollDuration);
  });
console.log(document.querySelectorAll("input[type=button]")[0].value);
handleClick(document.querySelectorAll("input[type=button]")[0].id)
  $("input[type=button]").click(function(e){
    handleClick(this.id)
  });
  function handleClick(buttonID) {
    $("input[type=button]").parent('li').css({
      'border': 'none',
      'padding-bottom': '19px'
    });
    $('#'+buttonID).parent('li').css({
      'border-bottom': '6px solid #000',
    'padding-bottom': '19px'
    });
    // Declare variables
    var table, tr, td, i, txtValue, pDate, clickedYear;
    clickedYear = buttonID.split('_')[1];
    table = document.getElementById("listTable");
    tr = table.getElementsByTagName("tr");


    // Loop through all table rows, and hide those who don't match the search query
    for (i = 0; i < tr.length; i++) {
      td = tr[i].getElementsByTagName("td")[0];
      if (td) {
        txtValue = td.textContent || td.innerText;
        pDate = td.getElementsByTagName("p")[0].innerHTML;
        var d = new Date(pDate);
        var pYear = d.getFullYear();
        if (pYear.toString() === clickedYear.toString()) {
          tr[i].style.display = "";
        } else {
          tr[i].style.display = "none";
        }
      }
    }
  }
})

