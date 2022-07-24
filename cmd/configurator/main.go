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

	flag.CommandLine.Usage = func() {
		o := flag.CommandLine.Output()
		_, _ = fmt.Fprint(o, "\nUsage: configurator [ OPTIONS ] [COMMAND | help]\n")
		_, _ = fmt.Fprint(o, "Where COMMAND := [check | makeconfigset | setupsolr | startsolr | stopsolr | starttika | stoptika | deleteall | downloadapp]\n")
		_, _ = fmt.Fprint(o, "      OPTIONS:\n")
		flag.PrintDefaults()
	}

	corename := flag.String("corename", "", "solr corename (with setupsolr)")
	flag.Parse()

	actioname := "check"
	if len(flag.Args()) == 0 {
		kassiscore.CheckConfigAndConnections("error-only")
		return
	} else if len(flag.Args()) == 1 {
		actions := []string{"check", "makeconfigset", "setupsolr", "startsolr", "stopsolr", "starttika", "stoptika", "deleteall", "downloadapp"}
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
	case "check":
		kassiscore.CheckConfigAndConnections("full")
	case "makeconfigset":
		kassiscore.GenerateDefaultConfigSet()
	case "setupsolr":
		err := kassiscore.SetupSolr(*corename)
		if err != nil {
			fmt.Println(err)
		}
	case "startsolr":
		cfg := kassiscore.LoadConfig()
		kassiscore.StartSolr(cfg)
	case "stopsolr":
		cfg := kassiscore.LoadConfig()
		kassiscore.StopSolr(cfg)
	case "starttika":
		cfg := kassiscore.LoadConfig()
		err := kassiscore.StartTika(cfg)
		if err != nil {
			fmt.Println(err)
		}
	case "stoptika":
		cfg := kassiscore.LoadConfig()
		err := kassiscore.StopTika(cfg)
		if err != nil {
			fmt.Println(err)
		}
	case "deleteall":
		cfg := kassiscore.LoadConfig()
		fmt.Println("clear documents?(Y/N)")
		scanner := bufio.NewScanner(os.Stdin)
		for scanner.Scan() {
			if scanner.Text() == "Y" {
				break
			}
			if scanner.Text() == "N" {
				return
			}
		}
		err := kassiscore.SolrClearDocument(cfg.Solr.Serveruri, cfg.Solr.Corename)
		if err != nil {
			fmt.Println(err)
		}
	case "downloadapp":
		kassiscore.DownloadApps()
	}

}
