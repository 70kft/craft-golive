(function ($) {
  'use strict';

  var FirstRun = {
    shade: '#golive-first-run',
    modal: '#golive-first-run .modal',
    leftButton: '#golive-first-run .btn-left',
    rightButton: '#golive-first-run .btn-right',
    slides: '#golive-first-run .slide',
    init: function () {
      this.addEventListeners();
    },
    getActiveSlide: function () {
      var FirstRun = this;
      var activeIndex = 0;

      $(FirstRun.slides).each(function (index) {
        if (!$(this).hasClass('to-left') && !$(this).hasClass('to-right')) {
          activeIndex = index;
        }
      });

      return activeIndex;
    },
    setActiveSlide: function (slideIndex) {
      var FirstRun = this,
        slides = $(FirstRun.slides),
        oldActiveSlide = this.getActiveSlide(),
        newActiveSlide;

      if(slideIndex === oldActiveSlide) {
        return;
      }

      $(FirstRun.slides).each(function (index) {
        if(slideIndex === index) {
          newActiveSlide = index;
        }
      });

      // Take out the old slide
      if(oldActiveSlide < newActiveSlide) {
        $(slides[oldActiveSlide]).addClass('to-left');
      }
      else {
        $(slides[oldActiveSlide]).addClass('to-right');
      }
      // Bring in the active slide
      $(slides[newActiveSlide]).removeClass('to-left to-right');

      // Show/hide buttons if at the edges
      if(newActiveSlide === 0) {
        $(FirstRun.leftButton).removeClass('show');
      }
      else if(newActiveSlide === $(FirstRun.slides).length - 1) {
        $(FirstRun.rightButton).removeClass('show');
      }
      else {
        $(FirstRun.leftButton).addClass('show');
        $(FirstRun.rightButton).addClass('show');
      }

    },
    addEventListeners: function () {
      var FirstRun = this;

      $(document).on('click', FirstRun.rightButton, function (e) {
        e.preventDefault();

        var activeIndex = FirstRun.getActiveSlide();
        var nextSlide =
          (activeIndex < $(FirstRun.slides).length - 1) ?
            activeIndex + 1 :
            activeIndex;
        FirstRun.setActiveSlide(nextSlide);
      });

      $(document).on('click', FirstRun.leftButton, function (e) {
        e.preventDefault();

        var activeIndex = FirstRun.getActiveSlide();
        var nextSlide =
          (activeIndex > 0) ?
            activeIndex - 1 :
            activeIndex;
        FirstRun.setActiveSlide(nextSlide);
      });

      $(document).on('click', '#golive-first-run .close-modal', function (e) {
        e.preventDefault();
        $(FirstRun.modal).addClass('to-bottom');
        $(FirstRun.shade).css('opacity',0);
        setTimeout(function () {
          $(FirstRun.shade).remove();
          history.pushState({}, '', window.location.pathname);
        }, 300);
      });
    }
  };

  FirstRun.init();
})(jQuery);
