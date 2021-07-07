// eslint-disable-next-line no-unused-vars
jQuery(function($) {
  function handleResponsive(maxWidth) {
    var allDesktop = document.getElementsByClassName('desktopTd');
    var allMobile = document.getElementsByClassName('mobileTd');
    document.getElementById("showMoreTitle").innerHTML = 'Valitse vuosi';
    if (maxWidth.matches) { // If media query matches
      for (var i = 0; i < allDesktop.length; i++) {
        allDesktop[i].style.display = 'none';
      }
      for (var i = 0; i < allMobile.length; i++) {
        allMobile[i].style.display = 'block';
      }
    } else {
      for (var i = 0; i < allDesktop.length; i++) {
        allDesktop[i].style.display = 'block';
      }
      for (var i = 0; i < allMobile.length; i++) {
        allMobile[i].style.display = 'none';
      }
    }
  }
  var maxWidth = window.matchMedia("(max-width: 544px)")
  handleResponsive(maxWidth) // Call listener function at run time
  maxWidth.addListener(handleResponsive)

  handleClick(document.querySelector("input[type=button]").id);

  $("input[type=button]").click(function(e){
    handleClick(this.id)
  });
  function isOverflown(element) {
    return element.scrollHeight > element.clientHeight || element.scrollWidth > element.clientWidth;
  }

  var els = document.getElementsByClassName('menu');
  for (var i = 0; i < els.length; i++) {
    var el = els[i];
    if (isOverflown(el)) {
      var fitCount = Math.floor((el.clientWidth - 100) / 57)
      $('ul.menu').each(function(){
        let count = 0;
        var list=$(this),
          select=$(document.getElementById("responsiveMenu")).insertAfter($(this)).change(function(){
          });
        $('>li input', this).each(function(){
          count ++;
          if (count < fitCount) {
            return;
          }
          else if (count >= fitCount) {
            $(this).remove();
            var span =  document.createElement('span');
            span.innerHTML = this.value;
            span.dataset.value = this.value;
            span.className = 'custom-option';
            document.getElementById("custom-options").appendChild(span);
          }
        });
      });
    }

  }
  for (const dropdown of document.querySelectorAll(".custom-select-wrapper")) {
    dropdown.addEventListener('click', function () {
      this.querySelector('.custom-select').classList.toggle('open');
      if (this.querySelector('.custom-select').classList.contains('open')) {
        document.getElementById("arrow-down").style.display = "none"
        document.getElementById("arrow-up").style.display = "block"
        document.getElementById("custom-select__trigger").style.borderBottom = '6px solid #000';
        $("input[type=button]").parent('li').css({
          'border': 'none',
          'padding-bottom': '19px'
        });
      } else {
        document.getElementById("custom-select__trigger").style.borderBottom = 'none';
        document.getElementById("arrow-down").style.display = "block"
        document.getElementById("arrow-up").style.display = "none"
      }
    })
  }

  for (const option of document.querySelectorAll(".custom-option")) {
    option.addEventListener('click', function () {
      handleClick(this.value);
      if (!this.classList.contains('selected')) {
        this.parentNode.querySelector('.custom-option.selected').classList.remove('selected');
        this.classList.add('selected');
        document.getElementById("arrow-down").style.display = "block"
        document.getElementById("arrow-up").style.display = "none"
        this.closest('.custom-select').querySelector('.custom-select__trigger span').textContent = this.textContent;
      }
    })
  }

  window.addEventListener('click', function (e) {
    for (const select of document.querySelectorAll('.custom-select')) {
      if (!select.contains(e.target)) {
        document.getElementById("arrow-down").style.display = "block"
        document.getElementById("arrow-up").style.display = "none"
        select.classList.remove('open');
      }
    }
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
    document.getElementById("arrow-down").style.display = "block"
    document.getElementById("arrow-up").style.display = "none"
    document.getElementById("custom-select__trigger").style.borderBottom = 'none';
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
        pDate = tr[i].getElementsByTagName("p")[0].innerText;
        var d = new Date(pDate.replace(/\./g, '/'));
        var pYear = d.getFullYear();
        if (pYear.toString() === clickedYear.toString()) {
          tr[i].style.display = "";
        } else {
          tr[i].style.display = "none";
        }
      }
    }
  }

  $(".custom-options:empty").parent().parent().hide();
  $(".item:first-child:empty").parent().hide();
})
