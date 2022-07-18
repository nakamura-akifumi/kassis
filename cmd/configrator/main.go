package main

import (
	"bufio"
	"flag"
	"fmt"
	kassiscore "github.com/nakamura-akifumi/kassis"
	"os"
)

func main() {

	fmt.Printf("kassis configrator. version %s (Revison:%s)\n", kassiscore.VERSION, kassiscore.REVISION)
	fmt.Println("Main function started")

	generateDefaultConfigSet := flag.Bool("generate-default-configset", false, "generate default configset to current directory")
	setupSolr := flag.Bool("setup-solr", false, "setup solr schema")
	deleteAlldata := flag.Bool("delete-alldata", false, "delete all solr data")
	startSolr := flag.Bool("start-solr", false, "Start solr")
	stopSolr := flag.Bool("stop-solr", false, "Stop solr")
	downloadapp := flag.Bool("download-app", false, "download apps")
	corename := flag.String("corename", "", "solr corename")
	flag.Parse()

	fmt.Println("generateDefaultConfigSet:", *generateDefaultConfigSet)
	fmt.Println("setupSolr:", *setupSolr)
	fmt.Println("startSolr:", *startSolr)
	fmt.Println("stopSolr:", *stopSolr)
	fmt.Println("deleteAlldata:", *deleteAlldata)
	fmt.Println("downloadapp:", *downloadapp)
	fmt.Println("corename", *corename)

	if *generateDefaultConfigSet == true {
		kassiscore.GenerateDefaultConfigSet()
	} else if *setupSolr == true {
		err := kassiscore.SetupSolr(*corename)
		if err != nil {
			fmt.Println(err)
		}
	} else if *deleteAlldata == true {
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
		kassiscore.SolrClearDocument(cfg.Solr.Serveruri, cfg.Solr.Corename)
	} else if *startSolr == true {
		cfg := kassiscore.LoadConfig()
		kassiscore.StartSolr(cfg)
	} else if *stopSolr == true {
		cfg := kassiscore.LoadConfig()
		kassiscore.StopSolr(cfg)
	} else if *downloadapp == true {
		kassiscore.DownloadApps()
	} else {
		kassiscore.CheckConfigAndConnections()
	}

}
