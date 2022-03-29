package main

import (
	"github.com/fsnotify/fsnotify"
	"github.com/labstack/echo/v4"
	"github.com/labstack/echo/v4/middleware"
	"github.com/nakamura-akifumi/kassis"
	"html/template"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"
)

func logFormat() string {
	var format string
	format += "time:${time_rfc3339}\t"
	format += "host:${remote_ip}\t"
	format += "forwardedfor:${header:x-forwarded-for}\t"
	format += "req:-\t"
	format += "status:${status}\t"
	format += "method:${method}\t"
	format += "uri:${uri}\t"
	format += "size:${bytes_out}\t"
	format += "referer:${referer}\t"
	format += "ua:${user_agent}\t"
	format += "reqtime_ns:${latency}\t"
	format += "cache:-\t"
	format += "runtime:-\t"
	format += "apptime:-\t"
	format += "vhost:${host}\t"
	format += "reqtime_human:${latency_human}\t"
	format += "x-request-id:${id}\t"
	format += "host:${host}\n"
	return format
}

/*
type Template struct {
	templates *template.Template
}
func (t *Template) Render(w io.Writer, name string, data interface{}, c echo.Context) error {
	return t.templates.ExecuteTemplate(w, name, data)
}
*/

const debug = true

// Renderer is a custom html/template renderer for Echo framework
type Renderer struct {
	templates  *template.Template
	whatchDir  string
	tempateExt string
}

func New() *Renderer {
	return &Renderer{}
}

// Render renders a template document
func (r *Renderer) Render(w io.Writer, name string, data interface{}, c echo.Context) error {

	// Add global methods if data is a map
	if viewContext, isMap := data.(map[string]interface{}); isMap {
		viewContext["reverse"] = c.Echo().Reverse
	}
	// Hot reload doesn't work correctly with base template
	//if r.templates.Lookup("base"+r.tempateExt) != nil {
	//	t := r.templates.Lookup(name)
	//	return t.ExecuteTemplate(w, "base", data)
	//}
	return r.templates.ExecuteTemplate(w, name, data)
}

// LoadTemplates finds and loads templates by specified pattern
func (r *Renderer) LoadTemplates(pattern string) {
	r.whatchDir = filepath.Dir(pattern) + "/"
	r.tempateExt = filepath.Ext(pattern)
	if r.whatchDir == "" {
		log.Fatal("Template directory not found!")
	}
	if r.tempateExt == "" {
		log.Fatal("Template extension can't be recognized!")
	}
	log.Print("Directory to watch: ", r.whatchDir)
	log.Print("Template file extension: ", r.tempateExt)
	r.templates = template.Must(template.ParseGlob(pattern))
}

func (r *Renderer) StartFSWatcher() {
	if r.templates == nil {
		log.Fatal("StartFSWatcher method should be executed only after LoadTempates method")
	}
	watcher, err := fsnotify.NewWatcher()
	if err != nil {
		log.Fatal("Failed to create FS watcher: ", err)
	}

	go func() {
		// used as intermediate storage for updated templates
		updatedTemplates := map[string]bool{}
		tick := time.NewTicker(time.Millisecond * 500)

		for {
			select {
			case <-tick.C:
				templates := make([]string, 0)
				for tp, isUpdated := range updatedTemplates {
					if isUpdated {
						updatedTemplates[tp] = false
						templates = append(templates, tp)
					}
				}
				if len(templates) > 0 {
					r.updateTemplates(templates...)
				}

			case event, ok := <-watcher.Events:
				if !ok {
					return
				}
				// as jetbrains IDE uses "safe write" it must also catch RENAME
				if event.Op&fsnotify.Write == fsnotify.Write || event.Op&fsnotify.Rename == fsnotify.Rename {
					// filters template files by extension
					if strings.HasSuffix(event.Name, r.tempateExt) {
						updatedTemplates[event.Name] = true
					}
				}
			case err, ok := <-watcher.Errors:
				if !ok {
					return
				}
				log.Print("Template directory watcher returned error: ", err)
			}
		}
	}()

	err = watcher.Add(r.whatchDir)
	if err != nil {
		log.Fatal(err)
	}
}

// updateTemplates is a helper called by
// a StartWatcher endless loop to reload updated templates
func (r *Renderer) updateTemplates(tpl ...string) {
	log.Print("Template has been reloaded:", tpl)
	r.templates = template.Must(r.templates.ParseFiles(tpl...))
}

func NewRouter() *echo.Echo {
	e := echo.New()
	r := New()
	r.LoadTemplates("views/*.html")
	r.StartFSWatcher()
	e.Renderer = r

	/*
		t := &Template{
			templates: template.Must(template.ParseGlob("views/*.html")),
		}
		e.Renderer = t
	*/
	//g := e.Group("/admin")
	//g := e.Group("/api")

	e.Static("/static", "assets")
	e.GET("/", func(c echo.Context) error {
		return c.String(http.StatusOK, "Hello, World!")
	})
	e.File("/", "public/index.html")

	e.GET("/api/materials", kassiscore.HandlerGetApiMaterials)
	e.GET("/materials", kassiscore.HandlerGetMaterials)

	return e
}

func main() {
	router := NewRouter()
	router.HideBanner = true
	router.HidePort = true

	logger := middleware.LoggerWithConfig(middleware.LoggerConfig{
		Format: logFormat(),
		Output: os.Stdout,
	})
	router.Use(logger)
	router.Use(middleware.Recover())
	router.Logger.Fatal(router.Start(":1323"))
}
