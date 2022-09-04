package main

import (
	"bufio"
	"flag"
	"fmt"
	kassiscore "github.com/nakamura-akifumi/kassis"
	"os"
	"strings"
)

func main() {

	fmt.Printf("kassis configurator. version %s (Revison:%s)\n", kassiscore.VERSION, kassiscore.REVISION)
	fmt.Println("Main function started")

	cfg := kassiscore.LoadConfig()
	defautcorename := "kassiscore"
	if cfg != nil {
		defautcorename = cfg.Solr.Corename
	}

	corename := flag.String("c", defautcorename, "solr corename (with setupsolr or deleteall)")
	flag.CommandLine.Usage = func() {
		o := flag.CommandLine.Output()
		_, _ = fmt.Fprint(o, "\nUsage: configurator [ OPTIONS ] [COMMAND | help]\n")
		_, _ = fmt.Fprint(o, "Where COMMAND := [check | makeconfigset | downloadapp | startsolr | setupsolr | starttika | stopsolr | stoptika | deleteall]\n")
		_, _ = fmt.Fprint(o, "      OPTIONS:\n")
		flag.PrintDefaults()
	}
	flag.Parse()

	//fmt.Println(len(flag.Args()), flag.NArg())
	//fmt.Println("corename:", *corename)

	actioname := "check"
	if flag.NArg() == 0 {
		kassiscore.CheckConfigAndConnections("error-only")
		return
	} else if flag.NArg() == 1 {
		actions := []string{"check", "configset", "setupsolr", "startsolr", "stopsolr", "starttika", "stoptika", "deleteall", "downloadapp"}
		if kassiscore.ArrayContains(actions, strings.ToLower(flag.Arg(0))) == false {
			fmt.Println("error: action is invalid")
			flag.CommandLine.Usage()
			return
		}
		actioname = strings.ToLower(flag.Arg(0))
	} else {
		flag.CommandLine.Usage()
		return
	}

	fmt.Println("actionname:", actioname)
	fmt.Println("corename:", *corename)

	switch actioname {
	case "help":
		flag.CommandLine.Usage()
	case "check":
		kassiscore.CheckConfigAndConnections("full")
	case "configset":
		kassiscore.GenerateDefaultConfigSet()
	case "setupsolr":
		err := kassiscore.SetupSolr(*corename)
		if err != nil {
			fmt.Println(err)
		}
	case "startsolr":
		kassiscore.StartSolr(cfg)
	case "stopsolr":
		kassiscore.StopSolr(cfg)
	case "starttika":
		err := kassiscore.StartTika(cfg)
		if err != nil {
			fmt.Println(err)
		}
	case "stoptika":
		err := kassiscore.StopTika(cfg)
		if err != nil {
			fmt.Println(err)
		}
	case "deleteall":
		if cfg == nil {
			fmt.Println("can not load configuration file.")
			return
		}
		fmt.Printf("[%s] delete documents? (Y/N)\n", *corename)
		scanner := bufio.NewScanner(os.Stdin)
		for scanner.Scan() {
			if scanner.Text() == "Y" {
				break
			}
			if scanner.Text() == "N" {
				return
			}
		}
		err := kassiscore.ClearSolrDocument(cfg.Solr.Serveruri, *corename)
		if err != nil {
			fmt.Println(err)
			return
		}
		fmt.Println("done")
	case "downloadapp":
		kassiscore.DownloadApps()
	}

}
