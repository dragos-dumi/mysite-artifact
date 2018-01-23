module.exports = function (grunt) {
  'use strict';

  grunt.loadNpmTasks('grunt-contrib-clean');
  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-contrib-copy');
  grunt.loadNpmTasks('grunt-contrib-cssmin');
  grunt.loadNpmTasks('grunt-contrib-less');
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-postcss');

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    clean: {
      css: {
        src: ['css']
      },
      libraries: {
        src: ['libraries']
      }
    },
    less: {
      options: {
        outputSourceFiles: true,
        sourceMap: true,
        relativeUrls: false,
        plugins: [
          require('less-plugin-glob')
        ]
      },
      screen: {
        files: {
          'css/style.css': 'less/style.less'
        }
      },
      print: {
        options: {
          outputSourceFiles: false,
          sourceMap: false
        },
        files: {
          'css/print-style.css': 'less/print-style.less'
        }
      }
    },
    cssmin: {
      screen: {
        files: [{
          expand: true,
          cwd: 'css',
          src: 'style.css',
          dest: 'css',
          ext: '.css'
        }]
      },
      print: {
        files: [{
          expand: true,
          cwd: 'css',
          src: 'print-style.css',
          dest: 'css',
          ext: '.css'
        }]
      }
    },
    postcss: {
      options: {
        processors: [
          require('autoprefixer')({browsers: 'last 4 versions'}), // add vendor prefixes
          require('postcss-flexibility')
        ]
      },
      dist: {
        src: ['css/**/*.css', '!css/print-style.css']
      }
    },
    watch: {
      configFiles: {
        options: {
          reload: true
        },
        files: ['Gruntfile.js', 'package.json']
      },
      screen: {
        files: ['less/**/*.less', '!less/print-style.less', 'images/*.svg'],
        tasks: 'less:screen'
      },
      print: {
        files: ['less/print-style.less'],
        tasks: 'less:print'
      }
    },
    copy: {
      libraries: {
        expand: true,
        cwd: 'node_modules',
        dest: './libraries/',
        src: [
            'flexibility/**',
            'bootstrap/**',
            'slick-carousel/**'
        ]
      }
    }
  });

  grunt.registerTask('default', ['less', 'watch']);

  grunt.registerTask('css', ['clean:css', 'less', 'postcss', 'cssmin']);

  grunt.registerTask('libraries', ['clean:libraries', 'copy:libraries']);

  grunt.registerTask('build', ['libraries', 'css']);

};
